## 1. AI system roles (admin)

- [x] 1.1 Add `role` to `StoreAiSystemRequest` (enum, default `chat`) with a closure rule using `AiProvider::supportsRole()` that names provider and role on failure. Make chat-only fields `exclude_unless:role,chat`, make `model`/`max_tokens` `required_if:role,chat`, and confirm the `max_tokens` column accepts a voice system without a value (store the column default if it is not nullable). Verify with a feature test covering a valid voice create, a rejected `lm-studio`+`audio` pairing, and a blank model accepted for a voice role.
- [x] 1.2 Add `role` to `UpdateAiSystemRequest`'s pending-duplicate branch only. Verify with feature tests: an established system ignores a submitted role, and a pending duplicate accepts one.
- [x] 1.3 Extend `feature_defaults.*` with `voice:dictation` and `voice:speech`, and add an after-validation check that each default matches the system's role, on both requests. Verify with feature tests for an accepted voice default, a rejected `audio`+`targeted-resume`, and a rejected `chat`+`voice:speech`.
- [x] 1.4 Store a default voice as `config.voice` for `audio` systems (accepted only for that role). Verify with a feature test that it round-trips through create and edit without disturbing other `config` keys.
- [x] 1.5 Filter `AiChatBotController::systems()` and `ApplicationPayload::selectableSystems()` with `->role(AiSystemRole::Chat)`. Verify with feature tests that an active voice system is absent from both payloads.
- [x] 1.6 Update `ai/systems/Form.tsx`: role select (disabled unless creating or a pending duplicate), providers filtered by role (add `elevenlabs` for voice roles), hide chat-only sections for voice roles, a "Default voice" field for `audio`, help text on `base_url` describing the local OpenAI-compatible pattern, and role-appropriate feature-default checkboxes. Verify `npm run build` succeeds and the form behaves correctly for each role in the browser.
- [x] 1.7 Show each system's role on `ai/systems/Index.tsx`, and pass `role` in the controller payload. Verify in the browser and with a feature test on the index payload.

## 2. Permission, config and shared props

- [x] 2.1 Create a migration adding the `use-voice` Keystone permission assigned to no role, with a working `down()`, following the `add_manage_comments_permission` migration. Run it on both `jasonvertucio` and `wink`, and verify the permission row exists in each.
- [x] 2.2 Add `config/voice.php` (`max_upload_kb`, `max_speech_chars`, `max_recording_seconds`, `cache_ttl_days`, `status_probe_timeout`, `status_cache_seconds`, `transcription_language` (blank by default, meaning the server detects the language), rate-limit values) and register the `voice-transcribe` and `voice-speech` rate limiters keyed on user id. Verify with `php artisan config:show voice`.
- [x] 2.3 Share a `voice` prop from `HandleInertiaRequests` and `HandleChatInertiaRequests`: `null` without `use-voice`, otherwise the endpoint URLs and `max_recording_seconds`. Verify with feature tests that a guest and a user without the permission get `null` on a persona page and an admin page, and a holder gets the URLs.

## 3. Voice backend

- [x] 3.1 Add the `/api/admin/voice` route group (`auth`, `can:use-voice`) in `routes/api-web.php` with `status`, `transcriptions` and `speech`. Verify with `php artisan route:list --path=api/admin/voice` and a feature test that a user without the permission gets 403 on each.
- [x] 3.2 Implement the status endpoint: resolve each feature's default, report `not_configured`/`inactive`/`unreachable`, probe `{base_url}/models` with the configured timeout using `Http::fake`-able calls, cache the result, and skip the probe when there is no `base_url`. Verify with feature tests for each reason and the available case.
- [x] 3.3 Implement transcription with a form request (MIME types, max size, optional `language`, falling back to a non-blank `voice.transcription_language`, otherwise none) calling `TranscriptionService::forFeature('voice:dictation', …)`, throttled with `voice-transcribe`. Verify with feature tests using `Transcription::fake()`: success returns `{text}`, an `audio/mp4` upload is accepted, an oversize or wrong-type upload returns 422, no language is sent when neither the request nor the config sets one, and nothing is written to storage.
- [x] 3.4 Implement a Markdown-to-speakable-text helper (CommonMark, then `strip_tags`, then collapsed whitespace). Verify with a unit test covering bold, links, lists, headings and code.
- [x] 3.5 Implement the speech cache service (key = sha256 of system id, model, resolved voice and text; `storage/app/voice-cache/{key}.mp3`) and the speech endpoint calling `SpeechService::forFeature('voice:speech', …)` on a miss, throttled with `voice-speech`, returning `audio/mpeg`. Verify with feature tests using `Audio::fake()`: a second identical request does not call the gateway, a changed `config.voice` misses, and empty or over-length text returns 422.
- [x] 3.6 Map every voice-service exception to `503 {"message":"Voice is unavailable."}` with a logged warning that includes the system id. Verify with a feature test that a connection failure gives 503 and the body contains neither the `base_url` host nor the provider's error text.
- [x] 3.7 Add the `voice:sweep-cache` command and schedule it daily next to `resume:sweep-expired-documents`. Verify with a feature test that it deletes files older than `cache_ttl_days` and keeps newer ones, and with `php artisan schedule:list`.

## 4. Voice frontend

- [x] 4.1 Add a `useVoice()` hook (shared prop, status fetch, refetch after a 503) and a small audio-player module that allows only one active clip. Verify `npm run build` succeeds and the hook returns null state when the prop is null.
- [ ] 4.2 Add the dictation microphone to `ChatInputArea` via `ChatInterface` (shown only when the voice prop is present): recording, transcribing and idle states, elapsed time, auto-stop at `max_recording_seconds`, Escape to cancel, MIME codec suffix stripped before upload, transcript inserted at the cursor, never sent, and messages for permission denied and unavailable. Verify in Chrome and Safari (or the iOS simulator) on the Discussion page that the transcript lands in the box and nothing is sent.
- [x] 4.3 Add the play/stop control to assistant rows in `ChatMessageBubble` (hidden while streaming and for interrupted empty replies). Verify in the browser that a Markdown reply plays without spoken markup and that starting a second reply stops the first.
- [x] 4.4 Add listen/stop to `cover-letters/Edit.tsx` and `resume/targeted/Edit.tsx`: split the current editor Markdown into blocks, play them in sequence with one block prefetched, and abort on stop. Verify in the browser that unsaved edits are read and that stop halts further requests (Network tab).
- [x] 4.5 Disabled states with tooltips showing the status reason, and no gradients or box shadows. Do a frontend-design pass and share screenshots of the composer (idle, recording, transcribing, disabled), a reply's play control and the document listen control with the developer before merge.
- [x] 4.6 Fix Prettier/ESLint in every touched file under `resources/js` so the husky hook passes. Verify with `npx eslint` on the changed files.

## 5. Documentation and wrap-up

- [x] 5.1 Add a "Voice (admin)" section to CLAUDE.md covering the role field, the `openai`+`base_url` local-server pattern and why, `use-voice`, the endpoints, the cache and sweep, GPU memory guidance, and the code-talker `supportsRole()` follow-up. Add `ai-system-voice-roles`/voice notes where CLAUDE.md lists the AI system admin. Verify by review.
- [x] 5.2 Write the developer-owned production steps (Speaches on the LM Studio host bound to the WireGuard address, the two AiSystems, feature defaults, granting `use-voice`, migrations) into the PR description, marked as developer-owned. Verify the PR body lists each step.
- [x] 5.3 Run the voice and AI-system feature tests (`php artisan test --compact --filter=Voice` and the AI system test files) and confirm they pass, then ask the developer whether to run the full suite.

## 6. Voice Systems pages and form layout (revised after review)

- [x] 6.1 List only chat systems on the AI Systems index, and add a Voice Systems page (`/admin/ai/voice-systems`) with its own index, create and edit forms that save through the existing store/update endpoints. Redirect each edit page to the other for a system of the wrong role, and add a Voice Systems entry to the AI Tools navigation. Verify with feature tests for both lists, both redirects, the post-save redirect, and an update that omits `config`.
- [x] 6.2 Relayout the shared provider block: Name full width; Provider, API Key and Model in one row (one per line when narrow); Base URL on its own line; API Version, Max Tokens, Context Length and Temperature as a two-by-two grid at every width. Remove the role field from the chat form. Verify `npm run build` and screenshots at desktop and phone width, shared with the developer.

## 7. Role-scoped models

- [x] 7.1 Add `ChatSystem` and `VoiceSystem` (subclasses of code-talker's `AiSystem` with a global `role` scope), pinning table, foreign key and morph class through `IsAiSystemSubset`. Use them in the persona and New Session pickers, `CompanyIntelSystems`, the AI Systems index and the Voice Systems pages (route-bound to `{voiceSystem}`). Verify with tests that each reads only its roles, that relations and counts resolve against `ai_systems` / `ai_system_id`, that creating sets or requires the role, and that a chat id is not found on the Voice Systems edit page.
