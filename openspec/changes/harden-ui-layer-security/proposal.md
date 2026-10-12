## Why

An audit of the code that serves the UI found five defects that let an
unauthenticated caller inject script into the homepage, read unpublished posts,
or fill the disk, and one that sends decrypted provider credentials to the
browser. None needs a redesign to fix, and none should wait for one.

## What Changes

- **Jellyfin webhook is authenticated and throttled, and media text is escaped.**
  `POST /api/event/@2028` accepts any caller today (`routes/api.php:18`), stores
  `Name`/`Album` verbatim (`LocalMediaController.php:107-110`), and the homepage
  prints the album name inside `{!! !!}` (`CurrentlyWatching.php:64`,
  `currently-watching.blade.php:12`). That is stored XSS on the homepage for
  anyone who can guess the path. **BREAKING** for the Jellyfin server: it must
  send the shared secret, or its webhooks are refused.
- **Unpublished posts are no longer readable by slug.**
  `BlogController::post()` looks a post up with no published filter
  (`BlogController.php:102`), so a scheduled or draft post renders for anyone who
  has its slug. Signed-in blog authors keep a preview.
- **AI system secrets become write-only in the admin.** The systems index and
  edit pages pass whole `AiSystem` models as page props
  (`AiSystemController.php:47,104`); `api_key`, `credentials` and
  `web_tool_policy` are decrypting casts and the model declares no `$hidden`, so
  every stored key is in the page JSON. The edit form reads `aiSystem.api_key`
  back into a field (`systems/Edit.tsx:37`).
- **Logout requires a POST with a CSRF token.** `GET /logout` is a closure that
  ends the session (`routes/auth.php:21`), so any page can log a user out with an
  image tag. The navigation's `/logout` anchor (`config.json:82`) becomes a form.
- **`/posts` is bounded.** It logs the whole body of any unauthenticated,
  CSRF-exempt, unthrottled request (`PostsIngestController.php:13`).
- **Résumé summary and skill text are sanitized on output.** Four views print
  AI-proposed, human-approved résumé text with `{!! !!}`
  (`about.blade.php:28`, `resume/header.blade.php:15`, `resume/skills.blade.php`).
  Low risk, closed in the same pass.

- **Four small defects in the same views are closed in passing.** The Alpine
  script is loaded unpinned from a CDN on every public and chat page
  (`layout.blade.php:119`, `chat.blade.php:49`, `chat-bare.blade.php:36`) and is
  now bundled through Vite; `env()` is called inside views (`home.blade.php:17`,
  `gtag.blade.php:2`, `wp-login.blade.php`) and becomes `config()`; the post page
  prints a JSON dump of the post (`blog/single.blade.php:31`) and loses it; and
  topic and tag listings include future-dated posts.

Not in this change: a visual redesign, admin or chat restructuring, and comment
formatting. The abandoned `ui-redesign-and-best-practices` change is not carried
forward.

## Capabilities

### New Capabilities

- `media-webhook-security`: who may post playback events, how often, and how
  media text is displayed.
- `blog-post-visibility`: which posts a visitor may read by address.
- `admin-secret-disclosure`: stored credentials are never sent to the browser.
- `session-logout`: how a session may be ended.
- `posts-ingest-limits`: the bounds on the `/posts` endpoint.

### Modified Capabilities

None.

## Impact

- **Code**: `routes/api.php`, `routes/auth.php`, `routes/web.php`,
  `LocalMediaController`, `CurrentlyWatching` and its view, `BlogController`,
  `Admin/AiSystemController`, `PostsIngestController`, `AppServiceProvider`
  (rate limiters), `config/services.php`, the navigation component and
  `resources/config/config.json`, `resources/js/admin/pages/ai/systems/*`,
  résumé display views, the `layout`, `chat`, `chat-bare`, `home`, `gtag` and
  `wp-login` views, `blog/single.blade.php`, `resources/js/app.js`, and
  `package.json` (adds `alpinejs`, approved by the site owner).
- **Deployment (developer-owned)**: set `JELLYFIN_WEBHOOK_SECRET` in the
  production `.env` and add the matching header in the Jellyfin webhook plugin
  **before** deploying, or playback tracking stops until both are in place.
- **No migrations.** Existing stored media titles are escaped on output, so no
  data cleanup is needed.
- **`vendor/jvjvjv/code-talker` is not edited.** Adding `$hidden` to the package
  model is the durable fix and is described in design.md for the package's own
  repository; this change projects fields in the host controller.
