## Why

The site already holds the data agents ask for on a visitor's behalf — the resume, the blog, the site profile — and three read-only `Laravel\Mcp\Server\Tool` classes already serve it to the site's own chat bot. But nothing serves them over the wire: `laravel/mcp` v0.8.2 is installed, and `McpServiceProvider::registerRoutes()` returns early when `routes/ai.php` is absent, so the application has never exposed a single MCP endpoint. Publishing one lets a recruiter's agent — or any MCP client — query the resume directly instead of scraping the page or driving the chat UI.

## What Changes

- Add a **public MCP endpoint** at `POST /mcp`, served by `App\Mcp\Servers\PublicServer` (the already-generated `ResumeServer` renamed, since the endpoint carries more than the resume).
- Register three **existing, unmodified-in-shape** read tools on it: `get-resume-data`, `get-recent-blog-posts`, `get-site-info`. No write tool is ever registered on this server.
- **Optional bearer authentication.** An anonymous call succeeds and returns the public projection. A call carrying a valid Sanctum personal access token is resolved to the owning `User`, and every existing Keystone permission check inside the tools then evaluates against that user — so a token holder sees privileged fields (salary history) that an anonymous caller does not.
- **Repair Sanctum's token table so tokens can be issued at all.** `personal_access_tokens.tokenable_id` is `bigint unsigned` from Sanctum's 2019 stock migration, while `User` uses `HasUuids` and a `char(36)` key — so `createToken()` has always failed and no personal access token has ever existed in this application. The token tier of this change is unreachable without fixing it.
- **Withhold direct contact details from anonymous callers of this endpoint.** `personal.email` and `personal.phone` are stripped for anonymous `/mcp` callers; `linkedin` and `url` remain as the contact path. This is a property of the endpoint, not of the tool — the public chat bots keep disclosing those fields exactly as they do today. Education, experience, skills and projects are included for anonymous callers.
- **Suppress in-progress resume drafts for anonymous callers.** `get-resume-data` currently reports `pending_revision_number` and will load any draft snapshot given a `revision_number` argument, with no identity check. On a public endpoint that discloses unpublished edits, so both are gated behind the `edit-resume` permission. **This also tightens the tool on the existing public chat-bot path**, which shares the same class.
- Define the `mcp` rate limiter. `routes/ai.php` already references `throttle:mcp`, and no such limiter is registered — the route currently throws on first request.
- Drop the two dead keys from `get-site-info`. Its filter asks for `skills` and `social`, neither of which exists in `resources/config/config.json`; `array_filter` silently discards them, so the tool already returns only `html_title`, `projects` and `interests` — all rendered on the public homepage. Removing the lines makes the filter state what it does, and stops a future unrelated `social` key from publishing itself.
- Keep tool classes where they are, under `app/Services/Mcp/Tools/`. `app/Mcp/` holds only what is specific to this transport: the server manifest, whose `$tools` array is the one place that says what is reachable over `/mcp`, and the contact-redacting wrapper above.

- **Log every call.** Each JSON-RPC request is recorded with its method, tool name, resolved identity (or anonymous), Cloudflare-aware client address, user agent, outcome and duration. The `initialize` call additionally captures the calling agent's self-reported name and version, which `laravel/mcp` surfaces on its `SessionInitialized` event — that field is the difference between knowing the endpoint is used and knowing *what uses it*.
- **Cache tool results, then throttle.** The underlying data changes on the order of weeks, so repeated calls are served from cache and cost nothing beyond a cache read. Rate limiting is therefore sized to stop floods and systematic harvesting rather than to protect the database, with separate anonymous and token-holder tiers.
- **Make the site machine-readable.** Publish a `Person` / `ProfilePage` JSON-LD block on the homepage — the site currently emits no structured data at all — and an `llms.txt` naming the `/mcp` endpoint. These reach crawlers and agents that will never speak MCP, and give the endpoint somewhere to be referenced from.

**Non-goals:** no write or edit tools on the public server; the `ResumeEdit` and `TargetedResume` tools stay chat-only. No `.well-known` discovery document and no public MCP registry listing — both are deferred pending a look at where those conventions actually stand. The human-facing `/ai` page that will document this endpoint is a separate change, so that writing its content does not block shipping the endpoint. No change to the `jvjvjv/code-talker` package is required.

- **Disable CodeTalker's bundled MCP server.** It was found *enabled* (`CODE_TALKER_MCP_ENABLED=true`), serving `fetch-web-page`, `http-request`, `search-web`, `scan-memories` and `get-temporal-information` at `/mcp/code-talker` behind a bare `auth:sanctum`. It exposes no resume data, but it accepts the same unscoped Sanctum tokens this change hands out for `/mcp` — so a token minted for a recruiter to read the resume would also let them drive `http-request` from the server. Rather than scope tokens by ability to close that, the endpoint is turned off, since nothing is known to consume it.

## Capabilities

### New Capabilities

- `public-mcp-server`: an unauthenticated-by-default MCP endpoint exposing a curated, read-only tool roster, where an optional bearer token raises the caller's identity and therefore what the tools disclose.
- `machine-readable-discovery`: the site's machine-facing description of itself — structured data for crawlers and agents, and a stated pointer to the MCP endpoint.

### Modified Capabilities

<!-- None. No existing spec states requirements about these tools' response shapes or reachability. -->

## Impact

**Code**

- `routes/ai.php` — path and server class (already created, currently pointing at `/mcp/resume` and an undefined throttle limiter)
- `app/Mcp/Servers/ResumeServer.php` → `PublicServer.php` — tool roster, name, instructions
- New middleware resolving an optional Sanctum bearer token onto the request's authenticated user
- `app/Providers/AppServiceProvider.php` — `RateLimiter::for('mcp')` with tiered limits
- New migration converting `personal_access_tokens.tokenable_id` to a UUID column
- New model, migration, factory and logger for MCP call records, plus a `SessionInitialized` listener and a retention sweep
- New homepage JSON-LD partial and a `public/llms.txt`
- `app/Services/Mcp/Tools/ChatBot/GetResumeDataTool.php` and `Concerns/LoadsResumeDataWithRevisionInfo.php` — draft-disclosure gating, and a seam so the response array can be projected before it is wrapped
- New endpoint-only tool under `app/Mcp/Tools/` redacting contact fields for anonymous callers
- `app/Services/Mcp/Tools/ChatBot/GetSiteInfoTool.php` — remove the `skills` and `social` dead keys

**Behavior outside the new endpoint**

- The public chat bot's `get-resume-data` stops reporting pending revision numbers to anonymous visitors. The authorized-persona resume-edit flow is unaffected, because it runs with an `edit-resume` holder's identity.
- Nothing else about the chat bots changes. Verified: `Fullerton`, `Emma` and `Ivan` are active with `required_permission = null` and their `AiSystem` allows `get-resume-data`, so an anonymous visitor can already obtain the full resume payload — contact fields included — through chat today. That stays true; the contact redaction is deliberately scoped to `/mcp`.

**Dependencies**

- None added. `laravel/mcp` v0.8.2 and `laravel/sanctum` v4 are already installed and `User` already has `HasApiTokens`. The `personal_access_tokens` table exists but was **not usable** — see the token-table repair above; its presence was confirmed during planning, its usability was not.

**Operations**

- No new port or vhost — the endpoint is served by the existing web server.
- `php artisan route:cache` must be re-run on deploy for the new route to exist in a cached-route environment.
- The token-table migration must run against **both** `jasonvertucio` and the `wink` test database, and then in production. Both were verified to hold zero token rows, so it drops and re-declares the columns rather than converting values.
- Tokens are minted per consumer and revoked individually.
- The call log grows with public traffic and needs a retention sweep, alongside the existing `SweepExpiredDocumentsCommand`.
- **Production `.env` must set `CODE_TALKER_MCP_ENABLED=false`.** The local environment has been changed; production is developer-owned and still serves `/mcp/code-talker` until it is. `.env.example` already carried `false`, so this was environment drift rather than a bad default.
