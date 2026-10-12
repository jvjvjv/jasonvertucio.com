Tests use `DatabaseTransactions` (never `RefreshDatabase`). Write each test
first and confirm it fails for the stated reason before fixing.

## 1. Jellyfin webhook and media display

- [x] 1.1 Add `tests/Feature/Media/WebhookAuthenticationTest.php`: correct secret → 200 and record written; missing, wrong, and unconfigured secret → 401 and no record; verify the 401 cases fail against current code
- [x] 1.2 Add `services.jellyfin.webhook_secret` (from `JELLYFIN_WEBHOOK_SECRET`) and a `VerifyJellyfinWebhook` middleware using `hash_equals`, applied to `POST /api/event/@2028`; update existing media webhook tests to send the secret; verify 1.1 and the existing media tests pass
- [x] 1.3 Define `media-webhook` (120/min) and `media-read` (60/min) limiters keyed on the Cloudflare-aware address and apply them to the three media routes; verify a test that request 121 within a minute gets 429
- [x] 1.4 Add a test rendering the homepage with a `LocalMedia` whose `album_name` and `title` contain `<img src=x onerror=alert(1)>` and assert the escaped text is present and `<img src=x` is not; verify it fails against current code
- [x] 1.5 Return plain text from `CurrentlyWatching::subtitle()`, add `albumName()`, and build the `<em>` in the template with `{{ }}`; verify 1.4 passes, a normal album still renders in emphasis, and `grep '{!!' resources/views/components/currently-watching.blade.php` is empty
- [x] 1.6 Add `JELLYFIN_WEBHOOK_SECRET=` to `.env.example` and document the header, the Jellyfin click-path (Webhook plugin → Generic destination → Add Request Header) and deploy order in README and `CLAUDE.md` (Media Tracking); verify both mention `X-Webhook-Secret`

## 2. Blog post visibility

- [x] 2.1 Add `tests/Feature/Blog/PostVisibilityTest.php`: published → 200; scheduled and draft → 404 for anonymous and for a signed-in user without blog access, with no `PostViewed` dispatched; blog author → 200 with a "not published" notice and no `PostViewed`; verify the scheduled and draft cases fail against current code (200 and 500)
- [x] 2.2 Scope `BlogController::post()` to `published()` unless the viewer passes the Canvas-authenticated check, pass `isPreview` to the view and show the notice, skip `PostViewed` on preview; verify 2.1 passes
- [x] 2.3 Add a test that submitting, editing and marking-spam a comment through an unpublished post's slug returns 404 and stores nothing; scope the comment routes' post resolution to published; verify it passes and existing comment tests still pass
- [x] 2.4 Add a test that a future-dated post is absent from its topic and tag listings and their counts; verify it fails, then restrict `topicList`/`tagList` to `published()` and verify it passes

## 3. AI system secrets

- [x] 3.1 Add `tests/Feature/Admin/AiSystemSecretDisclosureTest.php` asserting the Inertia props for the systems index, edit and logs pages, and the duplicate response, contain no `api_key`, `credentials` or policy header values, and do contain `has_api_key`; verify it fails against current code
- [x] 3.2 Add `AiSystemPresenter` with an explicit field allowlist and use it in every `AiSystemController` action that returns a system; verify 3.1 passes
- [x] 3.3 Add tests for the write contract: save without a key keeps it; save with a key replaces it; `clear_api_key` removes it; same three for `credentials`; a policy saved with a new domain keeps another domain's stored headers; verify they fail where the current form would blank the value
- [x] 3.4 Implement "keep unless supplied / explicit clear" in the update request and controller, including the server-side merge of `web_tool_policy` headers; verify 3.3 passes
- [x] 3.5 Let `fetchModels` take `ai_system_id` and fall back to that system's stored key; verify tests that a lookup with an id and no key returns models (HTTP faked) and that an unknown id is refused
- [x] 3.6 Update `systems/Edit.tsx`, `Form.tsx`, `JSONConfigEditor.tsx`, `WebToolPolicyEditor.tsx` and the types: empty secret fields with a "Key set" / "No key" indicator, a "Remove key" control, `ai_system_id` on model refresh; verify by hand: edit name only keeps the key working, replace works, remove works, model refresh works with an empty field (only reachable on a pending duplicate, since the model is locked on a saved system; the endpoint behavior is covered by `AiSystemSecretDisclosureTest`), and the page source contains no key
- [x] 3.7 Write the note for the `jvjvjv/code-talker` repository proposing `$hidden` on `AiSystem` (do not edit the package or `vendor/`); verify the note is delivered to the site owner

## 4. Logout

- [x] 4.1 Add `tests/Feature/Auth/LogoutTest.php`: `GET /logout` leaves the user authenticated and redirects home; `POST /logout` with a token ends the session; `POST` without a token is refused; verify the GET case fails against current code
- [x] 4.2 Replace the `GET /logout` closure with a non-destructive redirect and rely on Fortify's `POST /logout`; verify 4.1 passes and `route:list` shows `POST logout`
- [x] 4.3 Add `"method": "post"` to the logout entry in `resources/config/config.json`, render such entries as a CSRF form with a button in the Blade navigation components and as `router.post` in the admin navigation; update `GetSiteInfoTool`'s navigation filter if it keys on the href; verify a test that the signed-in homepage contains a logout form with a token and no `href="/logout"`, and by hand that log out works from the public site, admin and chat with the keyboard

## 5. /posts ingest

- [x] 5.1 Add `tests/Feature/PostsIngestTest.php`: normal request → 204 and one log entry; body over the cap → 413 and nothing logged; payload over the entry cap → truncated entry with marker; request 31 in a minute → 429; verify the last three fail against current code
- [x] 5.2 Add the `posts-ingest` limiter, the body cap, entry truncation and config values (`config/posts-ingest.php` or an existing config file, following project convention), and switch the `posts` log channel to `daily` with retention; verify 5.1 passes

## 6. Résumé text output

- [x] 6.1 Add a test rendering the homepage and résumé page with a summary and a skill containing `<script>`, `<a href="javascript:…">` and `<b onclick>`; assert none survive and `<strong>`/`<em>` do; verify it fails against current code
- [x] 6.2 Add the allowlist helper and use it at `about.blade.php:28`, `resume/header.blade.php:15`, `resume/skills.blade.php:17-18,28-29`; verify 6.1 passes and existing résumé display tests pass

## 7. View clean-ups

- [x] 7.1 Add a test that the public, chat and chat-bare layouts contain no `cdn.jsdelivr.net` Alpine script and that no file under `resources/views` calls `env(`, plus a test that the post page contains no pretty-printed JSON of the post; verify all fail against current code
- [x] 7.2 `npm install alpinejs`, import and start it in `resources/js/app.js`, remove the three CDN `<script>` tags; verify by hand in the browser that `x-data` behaviour (navigation menu, comment disclosures) still works on the public site and chat, and that no request goes to jsdelivr
- [x] 7.3 Replace `env()` in `home.blade.php`, `gtag.blade.php` and `wp-login.blade.php` with `config()`, and delete the JSON dump in `blog/single.blade.php`; verify 7.1 passes

## 8. Wrap-up

- [x] 8.1 Update `CLAUDE.md` Security Features (webhook secret, post visibility, Alpine bundled, write-only AI secrets, POST logout, `/posts` limits); verify each is described
- [x] 8.2 Run `php artisan test --compact`, `npm run lint`, `npm run build` and `openspec validate harden-ui-layer-security --strict`; verify all pass, recording any pre-existing failure separately
- [x] 8.3 Hand the site owner the deployment steps from design.md's Migration Plan, marked developer-owned; verify they are in the final summary
