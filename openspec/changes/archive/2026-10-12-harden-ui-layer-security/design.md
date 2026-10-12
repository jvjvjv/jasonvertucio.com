## Context

See proposal.md for the defects. Constraints:

- Production sits behind Cloudflare; `IpMiddleware` and the `comments` and `mcp`
  rate limiters already resolve the client address from its headers. New limiters
  use the same resolution.
- The cache driver is `file`; rate limiting works on it today (`comments`, `mcp`).
- `vendor/jvjvjv/code-talker` owns the `AiSystem` model and is not edited here.
- Deployment is developer-owned. Anything needing a production `.env` value or a
  Jellyfin setting is a documented step, not something this change performs.
- Tests use `DatabaseTransactions`; `phpunit.xml` pins `QUEUE_CONNECTION=sync`.

## Goals / Non-Goals

**Goals:**

- Close each defect at the boundary where the untrusted value enters or leaves,
  with a test that fails before the fix.
- Fail closed: an unconfigured secret refuses requests rather than allowing them.

**Non-Goals:**

- Scoping Sanctum tokens, the disabled CodeTalker MCP server, or IP banning.
- A general HTML sanitizer for the site. Only résumé text needs one.
- Rotating any provider key. Keys were exposed only to authenticated admins with
  `manage-ai-tools`-level access; whether to rotate is the owner's call (see Risks).

## Decisions

### 1. Webhook: a shared secret in a header, compared in constant time, in middleware

A `VerifyJellyfinWebhook` middleware reads `X-Webhook-Secret` and compares it with
`config('services.jellyfin.webhook_secret')` using `hash_equals`. Empty config
refuses everything.

- *Why a header, not a longer path?* The path is already the only secret
  (`@2028`) and it appears in access logs, proxies and this repository.
- *Why not HMAC over the body?* The Jellyfin webhook plugin sends custom headers
  but does not sign payloads.
- *Why not an IP allowlist?* The media server is on a residential connection.

The same route gets `throttle:media-webhook` — 120/min per address, well above a
player reporting progress every few seconds. The two read endpoints
(`/api/currently-watching`, `/api/media-stats`) are public by design and stay so,
with a 60/min throttle.

### 2. Escape media text where the HTML is assembled

`CurrentlyWatching::subtitle()` builds an HTML string, which is why the view needs
`{!! !!}`. The method returns plain text plus a separate `albumName()`, and the
template writes the `<em>` itself with `{{ }}`. No `{!! !!}` remains in the
component. Escaping at input was rejected: stored titles are also served as JSON,
where entity-encoded text would be wrong.

### 3. Post visibility: scope the lookup, branch once for authors

`BlogController::post()` queries `Post::published()` unless the viewer passes the
same check `@ifcanvasauthenticated` uses, in which case it queries all posts and
passes an `isPreview` flag to the view. A miss falls through to the existing
`topicsOrTags()` path, so an unpublished slug is indistinguishable from an unknown
one. `PostViewed` is not dispatched for a preview. The comment routes resolve
their post through the same published scope.

`topicList`/`tagList` also list future-dated posts today; they are scoped to
`published()` in this change (task 2.4).

### 4. AI system secrets: explicit projection out, "keep unless supplied" in

- **Out:** a single `AiSystemPresenter` (host-side) maps a model to the fields the
  pages read, replacing `api_key`, `credentials` and `web_tool_policy.credentials`
  with booleans (`has_api_key`, `has_credentials`, and per-domain
  `has_headers`). Index, edit, logs and any JSON endpoint returning a system use
  it. An allowlist, not `makeHidden`: a column added to the package later is
  hidden by default.
- **In:** the update request treats an absent or empty secret as "keep", a value
  as "replace", and an explicit `clear_api_key`/`clear_credentials` flag as
  "remove" — the same contract `AiMcpServerManager` already uses for `auth`.
  For `web_tool_policy`, submitted domains without header values are merged with
  the stored headers for that domain server-side.
- **Model lookup:** `fetchModels` accepts an optional `ai_system_id`; with no
  `api_key` in the request it loads that system (authorized by the route's
  existing permission) and uses its stored key — but only when the request's
  provider matches the system's and any `base_url` equals the stored one.
  Otherwise a lookup naming a system plus an address of the caller's choosing
  would deliver the stored key there (422 instead).
- **The key is replaceable on a saved system.** The form used to lock the key
  after creation ("change it by duplicating"). With a write-only key that lock is
  gone: provider and model stay locked, the key does not.
- **Known package defect (found while testing):** `ai_systems.credentials` is a
  MySQL `json` column but the model casts it `encrypted:array`, so any non-null
  write fails with "Invalid JSON text". Credentials therefore cannot be saved
  today; the write contract is implemented and covered at the merger level, and
  the presenter reads the raw column so it never decrypts. Fixing the column
  type belongs in `jvjvjv/code-talker`.
- **Duplicate** already runs server-side through `AiSystemManager::duplicate()`;
  it only needs its response projected.
- **Package follow-up (not done here):** `AiSystem` should declare
  `$hidden = ['api_key', 'credentials', 'web_tool_policy']`, and the
  `credentials` column should be `text`/`longText`. Described for the
  package repository; the host presenter does not depend on it.

### 5. Logout: Fortify's POST route, and a form in the navigation

Fortify already registers `POST /logout`. The `GET /logout` closure is replaced
by a redirect to the homepage that does not touch the session, so old bookmarks
do not 404. Navigation entries come from `config.json`; an entry gains
`"method": "post"`, which the Blade navigation renders as a `<form>` with `@csrf`
and a button, and the admin's React navigation as `router.post`. Styling the
button as a link is left to whichever shell is current.

### 6. `/posts`: throttle, cap, truncate, rotate

`throttle:posts-ingest` (30/min per address), a 16 KB body cap enforced from
`Content-Length` and the read body before any logging (413), a 4 KB per-entry cap
with a `truncated` marker, and the `posts` log channel switched to `daily` with a
retention count. The endpoint's purpose (an external caller posts to it) is
preserved; values live in config.

### 7. Résumé text: a tag allowlist

`{!! $summary !!}` and the skills output go through `SafeInlineHtml::clean()`,
which keeps `b`, `strong`, `i`, `em`, `br`. `strip_tags` with an allowlist is
**not** sufficient on its own: it keeps every attribute on a tag it allows, so
`<b onclick=…>` survives. The helper therefore strips attributes from the allowed
tags, then escapes whatever remains and restores only the bare allowed tags, so a
malformed tag degrades to visible text. If a link is ever wanted in a summary,
that needs a real sanitizer and is out of scope.

### 8. Small view clean-ups: bundle Alpine, `config()` for `env()`, drop the dump

- **Alpine** is added as an npm dependency and imported in `resources/js/app.js`
  (`window.Alpine = Alpine; Alpine.start()`); the three CDN `<script>` tags are
  removed. Every page that used the CDN already loads `app.js` through `@vite`.
  Bundling removes a third-party script from the trust boundary and the floating
  `3.x.x` version.
- **`env()` in views** becomes `config('app.env')`, `config('app.debug')`,
  `config('app.name')` and `config('app.url')`. `env()` returns null once config
  is cached, so these are latent bugs as well as style.
- **The JSON dump** in `blog/single.blade.php` is deleted outright.

## Risks / Trade-offs

- **Webhooks stop if the secret is deployed on one side only.** → Deployment
  order in the Migration Plan; the refusal is logged once per minute with the
  reason so it is diagnosable.
- **Keys were readable in page JSON by any admin who could open AI systems, and
  may sit in browser caches or session recordings.** → Not exploitable by an
  outsider, so rotation is not forced; listed as an owner decision.
- **"Keep unless supplied" hides a mistyped clear.** → Clearing is an explicit
  control, and the page shows "Key set" / "No key" after save.
- **The post lookup change could hide a post whose `published_at` is in a
  different timezone than expected.** → Uses Canvas's own `published()` scope,
  the same one the index already uses, so index and post agree.
- **A Jellyfin plugin version with no request-header option would make the
  secret impossible to send.** → Checked in Migration Plan step 1 before deploy.
  The fallback (a secret in the query string) leaks into access logs and is used
  only with the owner's approval.
- **A scheduled-post preview link shared by the author stops working for others.**
  → Intended.

## Migration Plan

1. Developer: add `JELLYFIN_WEBHOOK_SECRET` to production `.env`; in Jellyfin,
   Dashboard → Plugins → Webhook → the Generic destination → **Add Request
   Header**, key `X-Webhook-Secret`, value the same secret. Jellyfin sending an
   extra header is harmless before the deploy. Confirm the destination saved
   the header (reopen it) — the plugin's header option is documented only in
   third-party guides.
2. Deploy by the normal process; `php artisan optimize:clear`; restart the queue
   worker.
3. Verify: play something and confirm the homepage updates; `curl -X POST` the
   webhook without the header and expect 401.

Rollback: revert the merge. No data or schema changes.

## Open Questions

- Whether to rotate the stored provider keys after this ships.
