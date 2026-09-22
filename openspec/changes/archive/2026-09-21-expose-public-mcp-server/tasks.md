## 1. Endpoint scaffolding

- [x] 1.1 Rename `app/Mcp/Servers/ResumeServer.php` to `PublicServer.php` (class, `#[Name]`, `#[Version]`, and `#[Instructions]` describing the three tools for a calling agent) and verify `php artisan mcp:inspector` lists the server
- [x] 1.2 Register the public resume tool (the redacting subclass from 4.3), `GetRecentBlogPostsTool` and `GetSiteInfoTool` in `PublicServer::$tools`, leaving `$resources` and `$prompts` empty, and verify a `tools/list` call returns exactly `get-resume-data`, `get-recent-blog-posts`, `get-site-info`
- [x] 1.3 Point `routes/ai.php` at `Mcp::web('/mcp', PublicServer::class)` and verify `php artisan route:list --path=mcp` shows the POST route plus the package's 405 GET/DELETE routes
- [x] 1.4 Register `RateLimiter::for('mcp')` in `AppServiceProvider::boot()` returning an array of `Limit` objects per tier — anonymous 30/min + 200/hour keyed on `CF-Connecting-IP ?? $request->ip()`, token holders 120/min + 10,000/day keyed on the token id; verify a full session (initialize, tools/list, all three tools) is never throttled and that exceeding the anonymous burst returns 429

## 2. Optional bearer authentication

- [x] 2.0 Convert `personal_access_tokens.tokenable_id` to a UUID column (`uuidMorphs`), dropping and recreating the composite morph index; `User` is `HasUuids`/`char(36)` and the stock 2019 migration declared `bigint unsigned`, so `createToken()` had never worked. Verify the column is `char(36)` in both `jasonvertucio` and `wink`, and that a token can be issued and used

- [x] 2.1 Create the optional-auth middleware: no `Authorization` header passes through untouched; a bearer credential resolving through the `sanctum` guard calls `Auth::shouldUse('sanctum')`; an unresolvable credential aborts 401. Verify with a unit or feature test covering all three branches
- [x] 2.2 Apply the middleware in `routes/ai.php` ahead of `throttle:mcp` and verify an anonymous `tools/call` still succeeds while a garbage bearer token returns 401
- [x] 2.3 Confirm CodeTalker's existing `ToolContext` binding resolves the token's user on this route — assert inside a feature test that the tool sees the expected `userId` rather than assuming the binding fires

## 3. Draft suppression

- [x] 3.1 Gate `pending_revision_number` in `LoadsResumeDataWithRevisionInfo` behind the `edit-resume` permission of the current `ToolContext` user, omitting the key entirely for callers without it; verify with a unit test over the trait for both identities
- [x] 3.2 Make a `revision_number` request from a caller without `edit-resume` return the live resume with no `requested_revision_found`, `viewing_revision_number` or `viewing_revision_status` keys, and verify the response is byte-identical to the same caller's no-argument response
- [x] 3.3 Verify the authorized path is unchanged: a feature test asserting an `edit-resume` holder still receives `pending_revision_number` and can load a specific revision's snapshot

## 4. Public-disclosure projection

Review complete — decisions recorded in `proposal.md` and `design.md`. Anonymous `/mcp` callers get everything except salary, `personal.email` and `personal.phone`; education is included; the redaction is endpoint-scoped and the public chat bots are unchanged.

- [x] 4.1 Reviewed what `getAllEditableData()` returns and what the public site actually renders; decided to withhold `email` and `phone` from anonymous `/mcp` callers and to include education
- [x] 4.2 Reviewed `GetSiteInfoTool`'s projection; confirmed it already returns only `html_title`, `projects` and `interests` because `skills` and `social` are not keys in `config.json`, and that `links` (navigation, including `/admin`) is correctly excluded
- [x] 4.3 Extract a protected array-building method from `GetResumeDataTool::handle()` so `handle()` only wraps it in `Response::structured()`, leaving chat behavior identical; verify existing chat-path tests still pass
- [x] 4.4 Add the endpoint-only redacting subclass under `app/Mcp/Tools/` — its own `#[Name('get-resume-data')]` and `#[Description]` attributes (PHP does not inherit them), overriding the extracted method to unset `personal.email` and `personal.phone` when no user is resolved; verify a unit test covers both the anonymous and token-holder branches
- [x] 4.5 Confirm the subclass is invisible to CodeTalker's discovery: assert the chat tool registry still resolves exactly one handler named `get-resume-data`, and that it is the base class
- [x] 4.6 Remove the `skills` and `social` lines from `GetSiteInfoTool::handle()` and verify a test asserts the response keys are exactly `html_title`, `projects`, `interests`
- [x] 4.7 Confirm no tool outside the `PublicServer::$tools` roster is reachable by name over `/mcp` — feature test invoking `update-resume-section` against the endpoint and asserting an unknown-tool error

## 5. Test suite

- [x] 5.1 Add a feature test for the endpoint covering the initialize handshake, `tools/list`, and an anonymous `tools/call`, using `DatabaseTransactions` (never `RefreshDatabase` in this project) and verify it passes with `php artisan test --compact --filter=PublicMcpServer`
- [x] 5.2 Add coverage for the salary projection on both identities: anonymous receives experience entries with salary withheld, a token holder with `save-resume` receives the values
- [x] 5.3 Add coverage for the contact projection: anonymous receives no `email` or `phone` but keeps `linkedin` and `url`, and still receives education, experience, skills and projects; a token holder receives the contact fields
- [x] 5.4 Add coverage proving the redaction is endpoint-scoped — an anonymous chat-path invocation still returns `email` and `phone`
- [x] 5.5 Add coverage for the anonymous chat-bot path asserting the draft suppression does reach it, unlike the contact redaction
- [x] 5.6 Run the resume- and chat-related suites to confirm no regression, and report the actual output

## 6. Operations and documentation

- [ ] 6.1 **Developer-owned:** mint a token (`$user->createToken('mcp')`), connect a real MCP client to the deployed endpoint, and confirm both the anonymous and authenticated responses look right — including how the client reacts to the package's `WWW-Authenticate` header on a 401. The protocol mechanics are covered by `PublicMcpServerTest`; what remains is a real client against a real deployment
- [x] 6.2 Update `CLAUDE.md` with the new MCP section: the `/mcp` endpoint, the `PublicServer` roster as the audit surface, the optional-bearer identity rule, the endpoint-scoped contact redaction and why it is not in the shared tool, caching/throttle/logging, the discovery surfaces, and that `config('code-talker.mcp')` is now disabled
- [x] 6.4 Disable CodeTalker's bundled MCP server locally (`CODE_TALKER_MCP_ENABLED=false`) and verify `route:list --path=mcp` no longer shows `mcp/code-talker`
- [x] 6.6 **Developer-owned:** run the token-table migration in production (`php artisan migrate`). Verified zero token rows in both local databases; confirm the same in production before running, since the migration drops and re-declares the morph columns rather than converting values
- [x] 6.5 **Developer-owned:** set `CODE_TALKER_MCP_ENABLED=false` in the production `.env` and restart, so `/mcp/code-talker` stops accepting the same tokens `/mcp` uses. `.env.example` already carries `false`
- [x] 6.3 Write the deploy note for the developer (developer-owned, not run here): `php artisan route:cache` must be re-run for the route to exist in a cached-route environment; see the deploy summary reported at completion

## 7. Caching

- [x] 7.1 Add result caching to the three tools keyed on tool name, arguments and disclosure level, and verify a test asserts two identical anonymous calls hit the datastore once
- [x] 7.2 Verify the disclosure level is part of the key: a test in which an anonymous call is followed by a token-holder call with identical arguments, asserting each receives its own projection
- [x] 7.3 Invalidate resume-data cache entries when the live `ResumeVersion` changes, and verify a test publishing a new version sees it on the next call rather than after a TTL
- [x] 7.4 Hook blog-post cache invalidation to the events `FlushBlogFeedCache` already listens for, and verify publishing a post is reflected in the next recent-posts call

## 8. Call logging

- [x] 8.1 Create the call-record model, migration and factory (time, session id, method, tool name, user id nullable, client address, user agent, client name, client version, outcome, duration) via `php artisan make:model --migration --factory` and verify the migration runs against both `jasonvertucio` and `wink`
- [x] 8.2 Write the logging middleware, placed outside `throttle:mcp` so throttled and 401 requests are still recorded; read `method` and `params.name` from the JSON-RPC body and write the row after the response with outcome and duration. Verify tests covering a successful call, a throttled call and an unauthenticated rejection
- [x] 8.3 Add a `SessionInitialized` listener storing `clientInfo` name and version against the session id, tolerating a client that reports nothing; verify a test asserting the handshake still succeeds with absent `clientInfo`
- [x] 8.4 Verify later calls in a session are attributable to the handshake's client identity by asserting the join on session id in a feature test that initializes then calls a tool
- [x] 8.5 Assert no record contains any part of a bearer token — a test calling with a token and scanning the persisted row
- [x] 8.6 Add a retention sweep command with a configurable period, registered on the schedule alongside `SweepExpiredDocumentsCommand`, and verify a test that old records are deleted and recent ones kept

## 9. Discovery surfaces

- [x] 9.1 Add a JSON-LD `Person` / `ProfilePage` block to the homepage built from `getDisplayData()`, and verify a test asserting the rendered block carries name, title, summary and URL
- [x] 9.2 Assert the JSON-LD contains no `email` and no `telephone` — a dedicated test, since `Person` supports both and a well-meaning later edit would add them
- [x] 9.3 Verify the structured data tracks its source: a test changing the title at its source and asserting both the rendered page and the JSON-LD reflect it
- [x] 9.4 Add `public/llms.txt` naming the `/mcp` endpoint and stating the two access levels, and verify it is served as plain text
- [x] 9.5 Confirm `robots.txt` does not disallow `/mcp` or `/llms.txt` (it is currently `Disallow:` with an empty value, which permits everything) and verify with a request to each path
