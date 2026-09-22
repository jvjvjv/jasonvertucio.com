## Context

See `proposal.md` — Why. The constraints that shape the approach, all verified against the current tree:

- `laravel/mcp` v0.8.2 loads `routes/ai.php` through `Route::group([], $path)` — **no middleware group at all**. The endpoint gets no session, no CSRF, no `api` group. Globally appended middleware still runs, so `IpMiddleware`'s ban list already covers it.
- `Mcp::web()` registers `POST` for the protocol and `GET`/`DELETE` returning `405` by design, and always attaches `ReorderJsonAccept` and `AddWwwAuthenticateHeader`.
- **CodeTalker already binds `ToolContext`.** `CodeTalkerServiceProvider::register()` binds it to `ToolContext::forUser($app['auth']->user()?->getAuthIdentifier())`, documented as being for exactly this case — "a tool resolved outside the local chat loop — primarily the external MCP server". The chat loop overrides it per-conversation via `makeWith()`. So the identity plumbing exists; this change only has to make the default guard see the bearer token's user.
- Tool classes are already `Laravel\Mcp\Server\Tool` subclasses, discovered by CodeTalker from directories the app registers (`AppServiceProvider` registers `app_path('Services/Mcp/Tools')`). Directory layout is the app's choice, not the package's.
- `laravel/sanctum` v4 auto-registers the `sanctum` guard (`SanctumServiceProvider` merges `auth.guards.sanctum` at register time), `User` uses `HasApiTokens`, and `personal_access_tokens` exists. The `api` guard in `config/auth.php` is the legacy `token` driver and is not involved.
- `routes/ai.php` as it stands references `throttle:mcp`, and no `mcp` limiter is registered — the route would throw on its first request.

## Goals / Non-Goals

**Goals:**

- One endpoint, one explicit tool roster, readable at a glance.
- Identity is resolved once, in middleware; tools keep making their own permission decisions and stay unaware of the transport.
- The anonymous projection is the *same shape* as the authenticated one, so a consumer never branches on whether it has a token.

**Non-Goals:**

- OAuth. The package supports it (`config/mcp.php`, Passport), but it buys nothing here: there is one resource owner and a handful of hand-issued tokens.
- Reworking how CodeTalker discovers or builds tools. No package change.
- Scoping Sanctum tokens by ability. It would be the general fix for two endpoints sharing one credential, but with CodeTalker's server turned off there is only one endpoint, and an ability system with a single ability is ceremony.

## Decisions

### Server manifest in `app/Mcp/Servers/`, tools stay in `app/Services/Mcp/Tools/`

The `$tools` array on the server class becomes the single audit surface: reading it tells you exactly what is reachable over `/mcp`. Tools stay where CodeTalker discovers them, so each tool has one definition serving two transports.

*Alternative considered:* moving the three public tools into a `Tools/Public/` directory to mark them. Rejected — it would make "public" a property of a file's location rather than of the roster, and `AppServiceProvider` registers the whole `Tools/` tree with CodeTalker anyway, so the directory would not actually gate anything. A tool being in a directory named `Public` while a server elsewhere decides the real roster is two sources of truth.

*This also answers the "does it have to go back to CodeTalker" question: no.* The only package-owned coupling is the `ToolContext` constructor parameter, and CodeTalker already supplies a default binding for the non-conversation case.

### One server at `/mcp`, not one per subject area

`PublicServer` (the generated `ResumeServer`, renamed) carries all three tools. An agent asking "who is this person" wants the resume, the writing and the projects together; making it connect to three endpoints to assemble that is friction with no compensating benefit. `/mcp` is the well-known path to hand out.

*Alternative considered:* `/mcp/resume` + `/mcp/blog`. Rejected — the split buys independent throttles and rosters that nothing here needs, at the cost of a second connection for the common case.

### Sanctum's token table had to be repaired first

The planning artifacts recorded that `personal_access_tokens` exists. It does — but with `tokenable_id` as `bigint unsigned`, from Sanctum's 2019 stock migration and its `morphs('tokenable')`. `User` uses `HasUuids` with a `char(36)` key, so every `createToken()` call dies on `SQLSTATE[01000] Data truncated for column 'tokenable_id'`. **No personal access token has ever been issued in this application**; `HasApiTokens` has been decorative.

The whole token tier rests on this, so it is repaired here rather than deferred: the column becomes `uuidMorphs('tokenable')`.

Both databases were verified to hold **zero** token rows, so the migration drops the two morph columns and the composite index and re-declares them, rather than converting values in place. That is simpler and exact, and it is only correct while the table is empty — the migration says so, because a future reader finding rows there must rewrite it as a conversion.

The lesson worth keeping: *the table existed* was checked during planning and *the table worked* was not. Presence of a dependency is not evidence that it functions against this application's own models.

### Optional auth as custom middleware, not `auth:sanctum`

`auth:sanctum` rejects unauthenticated requests, which is the opposite of the requirement. The middleware instead:

1. No `Authorization` header → pass through. The default `web` guard has no session on this route, so `auth()->user()` is `null` and `ToolContext::forUser(null)` results. Anonymous, by construction.
2. `Authorization: Bearer …` present → resolve through the `sanctum` guard. Resolves to a user → `Auth::shouldUse('sanctum')` so CodeTalker's existing binding picks that user up. Does not resolve → **401**.

Rejecting a bad token rather than silently downgrading is a deliberate call: a caller with a revoked token that keeps getting `200`s full of blanks has no way to tell a permission boundary from an expired credential.

*Alternative considered:* a static shared secret in config. Rejected — with no `User` behind it there is nothing for `can('save-resume')` to evaluate, so every gate inside the tools would have to be re-expressed as "is this the magic token", duplicating the permission model.

### Draft suppression lives in the shared loading concern, not in the server

`LoadsResumeDataWithRevisionInfo` is where `pending_revision_number` and the `revision_number` lookup are produced, and it is shared by the chat path and the MCP path. Gating it there means the rule holds on both, and a future tool that uses the trait inherits it. Gating it in `PublicServer` would leave the anonymous chat-bot path leaking.

The gate is the `edit-resume` permission — the same permission that already gates every resume-edit and candidate-review tool — so a caller who may not edit drafts may not read them either.

A caller without the permission asking for a specific `revision_number` gets the **live resume**, with no `requested_revision_found` field. Returning an explicit "not found" would confirm or deny the existence of a given revision number, which is the disclosure being prevented.

### Contact redaction lives at the endpoint, not in the shared tool

Salary suppression and draft suppression are *permission* decisions, so they belong inside the tool and reach every caller of it. Withholding email and phone is not — the same data stays available to anonymous visitors through the public chat bots by explicit decision, so it cannot be expressed as "anonymous callers never see this".

It is therefore a property of the transport. `GetResumeDataTool::handle()` gains a seam — the array-building moves into a protected method that `handle()` wraps in `Response::structured()` — and an endpoint-only subclass under `app/Mcp/Tools/` overrides that method to call the parent and unset `personal.email` and `personal.phone` when no user is resolved. `PublicServer::$tools` registers the subclass instead of the base tool.

Two mechanics worth stating, because both fail silently otherwise:

- **The subclass needs its own `#[Name]` and `#[Description]` attributes.** PHP's `ReflectionClass::getAttributes()` does not return a parent's attributes, so an unannotated subclass would not inherit the tool name. It declares `#[Name('get-resume-data')]` so the wire-visible name is unchanged and agents see one tool.
- **It must live outside `app/Services/Mcp/Tools/`.** `AppServiceProvider` registers that whole tree with CodeTalker's discovery, which keys handlers by `name()` — a second class answering to `get-resume-data` inside that tree would collide with the base tool in the chat registry, with the winner decided by directory sort order. Placing it in `app/Mcp/Tools/` keeps it invisible to discovery and reachable only through the server manifest.

*Alternative considered:* a transport flag on `ToolContext` that the base tool consults. Rejected — it puts the tool back in the business of knowing how it was called, and the flag would have to be threaded through CodeTalker's binding, which is a package change this design otherwise avoids.

*Alternative considered:* redacting in middleware on the way out, by rewriting the JSON-RPC response body. Rejected — it would have to parse and rewrite MCP protocol framing to reach one field of one tool's payload, and would silently miss the field if the tool's response shape ever changed.

### Throttle for abuse, cache for cost

The instinct is to size the rate limit so the database survives. That is the wrong lever here, for two reasons discovered while reading the transport.

First, **the web transport is stateless and every JSON-RPC call is its own HTTP POST**. `Server::handle()` generates a session id on `initialize`, echoes it in a header, and persists nothing. So an ordinary session spends requests on ceremony before it asks anything useful:

```
  initialize        1 request   ceremony
  tools/list        1 request   ceremony
  get-resume-data   1 request   <-- the only one that touches data
  get-site-info     1 request
                   ---
                    4-5 requests, over in about two seconds
```

A REST-style 60/minute is therefore only twelve complete sessions per minute — it is counting the wrong unit.

Second, **the data barely changes**. The resume moves when a version is published, perhaps monthly; the post list moves when a post is published. So the primary defense is a cache, not a limiter: a harvester hitting the endpoint ten thousand times costs ten thousand cache reads rather than ten thousand queries. `FlushBlogFeedCache` is already wired to Canvas's publish events for exactly this, and resume invalidation keys naturally on the live `ResumeVersion`.

Cache keys must include the disclosure level, or an anonymous caller could be served a token holder's projection — the same field-level differences that make the redaction worth doing make a shared cache key a disclosure bug.

With cost handled by the cache, the limiter only has to stop floods and systematic harvesting, which means it can be generous. `RateLimiter::for()` accepts an array of `Limit` objects, giving each tier a burst window and a sustained one:

| | Per minute | Sustained | Keyed on |
| --- | --- | --- | --- |
| Anonymous | 30 | 200 / hour | `CF-Connecting-IP ?? $request->ip()` |
| Token holder | 120 | 10,000 / day | token id |

Thirty a minute is roughly six complete sessions from one address — past anything legitimate. Two hundred an hour is about forty sessions, far past curiosity and far short of harvesting. Without the Cloudflare header the proxy's address becomes the key and anonymous callers share one bucket; that is a known limitation of running behind a proxy, not a reason to key on something less accurate.

`IpMiddleware` is global and already covers this route, so a caller who repeatedly exhausts the limit is a candidate for the escalation paths the in-flight `unify-ip-ban-triggers` and `escalate-repeat-spam-to-ipban` changes are building. This change does not implement that link, but the signal is available to it.

### Logging: a row per request, enriched by the handshake

`laravel/mcp` ships exactly one event, `SessionInitialized`, and it carries the thing most worth having — `clientInfo` with the agent's self-reported `name`, `title` and `version`, plus the protocol version and capabilities. There is no per-tool-call event, but there does not need to be: every POST is one JSON-RPC call, so `method` and `params.name` are readable straight off the request body.

That splits cleanly into two hooks:

```
  POST /mcp
     |
     v
  [optional-auth]  resolves the user, or 401
     |
     v
  [mcp-log]        reads method + params.name from the body,
     |             writes the row after the response with
     |             outcome + duration
     v
  [throttle:mcp]
     |
     v
  Server::handle() fires SessionInitialized on `initialize`
     |             -> listener stores clientInfo against the session id
     v
  Tool::handle()
```

The session id is what joins them. `HttpTransport` reads `MCP-Session-Id` from the request, so every call after the handshake carries the id the handshake minted, and a row written mid-session can be attributed to the client identified at its start. Records store no part of the token — the resolved user id is the identity, since the token is a credential.

Ordering matters: the log middleware sits **outside** the throttle so that a throttled request is still recorded. A rejected call that leaves no trace is the one you most want to see.

*Alternative considered:* parsing MCP framing in a response-rewriting middleware to capture results as well as requests. Rejected — it buys little over recording the outcome, and couples the log to protocol framing that the package is free to change.

### CodeTalker's bundled MCP server is turned off, not scoped around

`config('code-talker.mcp')` was enabled in the live environment, registering `/mcp/code-talker` with `auth:sanctum` and the package's own five tools. It discloses no resume data, so it is not a hole in what this change publishes — but it accepts the *same* unscoped personal access tokens. Minting a token so someone can read the resume over `/mcp` would also hand them `http-request` against the server.

Two ways to break that coupling: give tokens abilities and gate each endpoint on one, or stop serving the second endpoint. Turning it off was chosen because nothing is known to consume it, and because an ability system introduced to guard one unused endpoint is machinery that must then be maintained and correctly applied forever after.

`fetch-web-page` appeared to be domain-restricted for a conversation-less caller — `code-talker.tools.web_fetcher.allowed_domains` is `['1f916.ai']` and the policy falls back to config when there is no `AiSystem` — but `http-request`'s scoping was never established, and turning the server off made the question moot. **If it is ever re-enabled, that question has to be answered first**, along with token ability scoping.

### Discovery surfaces: publish what is stable, defer what is not

MCP clients do not crawl, and there is no resolution path from a domain to its MCP server. An endpoint nobody has the URL for is an endpoint nobody calls, so the change publishes two descriptions of the site that agents already look for:

- **JSON-LD `Person` / `ProfilePage` on the homepage.** The site currently emits no structured data at all. This is the surface with the widest reach, because it is consumed by search and by agents that have never heard of MCP. It is built from `getDisplayData()` — the same source the page renders — so the two cannot disagree.
- **`llms.txt`**, naming the endpoint and stating the two access levels.

Both are static, under the site's own control, and carry no third-party commitment.

Deferred, deliberately: a `.well-known` discovery document and a public MCP registry listing. The first because the convention's status needs checking rather than guessing; the second because it puts the owner's name in a third-party catalog, which is a visibility decision that can be made later at no cost — a listing added afterwards points at surfaces this change already publishes.

**The JSON-LD must not carry email or phone.** The endpoint withholds those from anonymous callers by explicit decision; publishing them as structured data on an anonymously readable homepage would hand them to every crawler on earth and make the redaction theatre. `Person` supports `email` and `telephone`, which is exactly why this needs stating rather than assuming.

## Risks / Trade-offs

- **Public resume data is broader than the public `/resume` page.** The page requires auth or a share code; this endpoint does not. → Reviewed and settled. The precedent is real — `Fullerton`, `Emma` and `Ivan` are active, permissionless chat bots whose `AiSystem` allows `get-resume-data`, so the full payload is already reachable without auth. The endpoint nonetheless withholds email and phone, on the reasoning that a structured `tools/call` returning clean JSON is harvestable at machine speed in a way a conversational loop is not, and that the site renders no `mailto:` or `tel:` anywhere.
- **`AddWwwAuthenticateHeader` on a 401 advertises OAuth resource metadata**, which may lead a client to attempt an OAuth flow that this app does not serve. → Acceptable; the 401 body still says what happened. Worth confirming against a real client during verification.
- **A shared tool class now has two callers with different trust levels.** A future edit to `GetResumeDataTool` made with only the chat bot in mind changes what the public endpoint discloses. → The spec's scenarios cover both callers, including an explicit anonymous-chat-visitor scenario, so a regression fails a test rather than shipping.
- **The call log grows with public traffic.** An endpoint built to be called by strangers writes a row per call. → Bounded by a configurable retention period and a scheduled sweep, following the existing `SweepExpiredDocumentsCommand` precedent. Sized wrong, this table becomes the largest in the database.
- **Caching delays a published resume version.** An approved version would not reach callers until its cache entry expired. → Invalidate on the live `ResumeVersion` changing rather than relying on TTL expiry alone; the spec requires a new version to be visible on the next call.
- **JSON-LD is a second place the owner's identity is stated.** A hand-maintained block would drift from the resume. → Built from `getDisplayData()`, the same source the homepage renders, and the spec requires the two to move together.
- **Route caching.** `McpServiceProvider::registerRoutes()` skips registration when routes are cached and the app is not in console; caching is done in console, so the route is captured — but a deploy that does not re-run `route:cache` serves a stale table without the endpoint. → Deploy note, already in the proposal's Impact.
- **`get-site-info`'s filter did not say what it does.** It named five keys, two of which (`skills`, `social`) do not exist in `resources/config/config.json`, so `array_filter` discarded them and the tool returned only `html_title`, `projects` and `interests` — all rendered on the public homepage. → Reviewed and settled: nothing needed narrowing, but the dead keys are removed so an unrelated future `social` key cannot start publishing itself. The spec now pins the returned set explicitly.

## Migration Plan

Additive; nothing existing changes contract except the draft-suppression tightening on `get-resume-data`, which removes a field rather than changing one. The contact redaction touches only the new endpoint, so no existing caller sees a different payload.

1. Deploy. The endpoint is live and anonymous immediately.
2. Mint tokens per consumer with `$user->createToken(...)`; revoke individually.
3. Rollback is deleting `routes/ai.php` (or commenting the `Mcp::web` line) — the package then registers no MCP routes at all, and the chat bot is unaffected.
