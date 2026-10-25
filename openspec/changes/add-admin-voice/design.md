## Context

See proposal.md for motivation. Current state:

- **code-talker 0.18.0** is installed, and `2026_10_12_000002_add_role_to_ai_systems_table` has run. `AiSystem::role` is fillable, cast to `AiSystemRole` and defaults to `chat`. `SpeechService::forFeature()` and `TranscriptionService::forFeature()` resolve a system by feature key and refuse an inactive system or one with the wrong role.
- **The host does not use `AiSystemManager`.** `AiSystemController::store()` calls `AiSystem::create()` after `StoreAiSystemRequest`. Those requests copy the manager's rules by hand ("Mirrors … AiSystemManager's own …"), so the role and provider pairing check the package added in 0.17 is not applied.
- **The provider lock.** `UpdateAiSystemRequest` already locks provider, model and API key after creation, unless the system is a pending duplicate (`duplicated_at`). The role follows the same rule.
- **One composer for every chat surface.** Public personas, the application Discussion page and Company Intel chat all render `ChatInterface`, which renders `ChatInputArea`.
- **The local speech server.** It runs on the LM Studio GPU host and is reached over WireGuard. Its endpoints are OpenAI-compatible: `/v1/audio/transcriptions` and `/v1/audio/speech`.
- **The `openai` provider honours `base_url`.** `AiSystemProviderConfigurator::resolveUrl()` returns `$system->base_url` for any provider, and laravel/ai's `OpenAiGateway` posts to `{url}/audio/speech` and `{url}/audio/transcriptions`.

## Goals / Non-Goals

**Goals:**
- Voice systems are administered through the existing AI systems screens, with no manual SQL.
- One backend serves dictation, read-aloud and proofreading.
- Voice keeps working with no code-talker change.
- An unreachable home server degrades to disabled controls, never to a broken page or a leaked address.

**Non-Goals:**
- Voice for anyone other than holders of `use-voice`: no public persona voice, per-persona voices, or abuse controls beyond a rate limit.
- Streaming speech playback (the package returns whole files) and real-time or partial transcription while speaking.
- Automatic fallback to a cloud provider when the tunnel is down.
- Speaker diarization (local servers do not return `diarized_json`).
- Usage and cost tracking for voice (the package does not track it, and the local server has no per-call cost).

## Decisions

### 1. Local server as `provider=openai` with `base_url`, not `openai-compatible`
`AiProvider::supportsRole()` refuses voice roles for `openai-compatible` and `lm-studio`. A `provider=openai` system with `base_url=http://<wg-address>:8000/v1`, an explicit `model` (for example `Systran/faster-whisper-medium` or `kokoro`) and a placeholder API key goes through laravel/ai's OpenAI gateway unchanged.
- *Alternative: change `supportsRole()` in code-talker so `openai-compatible` can serve voice.* That is the more accurate label, and is noted as a follow-up for the package owner. Code-talker is not edited from this repo, and nothing here depends on that change.
- *Consequences:* the model must be set explicitly, because a blank model sends `gpt-4o-mini-tts` / `gpt-4o-transcribe-diarize`, which a local server does not know. This is administrator guidance, not validation: a real OpenAI voice system can legitimately leave the model blank. The gateway's `speed` and `instructions` parameters are ignored by local servers.

### 2. Speaches as the recommended server
A single container serves faster-whisper speech-to-text and Kokoro/Piper text-to-speech on OpenAI-compatible paths, and can unload idle models after a time-to-live. That matters because the GPU is shared with LM Studio.
- *Alternatives:* Kokoro-FastAPI plus whisper.cpp server (two processes, two systems' `base_url`s; it works too because each is just a `base_url`), or LocalAI (overlaps with LM Studio). Nothing in the app depends on the choice.
- Recommended models: a medium or turbo Whisper (about 1–3 GB of VRAM) and Kokoro (under 1 GB). Avoid large-v3 unless LM Studio's working set leaves room for it.

### 3. Role-aware validation lives in the host's form requests, using the package's predicates
`StoreAiSystemRequest` adds `role` (`Rule::enum(AiSystemRole::class)`) and a closure rule that calls `AiProvider::from($provider)->supportsRole($role)`, so the provider and role table has one owner (the package). Chat-only fields become `exclude_unless:role,chat`. `model` and `max_tokens` become `required_if:role,chat`. `feature_defaults.*` gains `voice:dictation` and `voice:speech`, with an after-validation check that each feature matches the role. `UpdateAiSystemRequest` validates `role` only in its `$pendingFirstEditRules` branch.
- *Alternative: switch the controller to `AiSystemManager::create()/update()`.* That is a larger refactor of a controller with its own capability normalization and secret merging (`AiSystemSecretMerger`), and out of scope.
- The default voice is stored in the existing `config` JSON column as `config.voice`. There is no schema change and no package change. The form shows it as a plain "Default voice" text field for the `audio` role.

### 3a. Voice systems get their own pages (revised during implementation)
The first build put a role field on the AI Systems form and a role column on its index. In use, voice systems were lost among chat systems, and the chat form's many chat-only fields made a voice system hard to read. Voice systems stay AiSystem records (the package's audio services need them), but `VoiceSystemController` lists them at `/admin/ai/voice-systems` with a short form of their own: name, what the system is used for (Dictation or Read aloud, as two large choices because that answer decides every field below), provider, key, model, base URL, default voice, the one feature default the role allows, and Active. Both forms save through `AiSystemController::store()/update()` so validation is not duplicated; the AI Systems index lists chat systems only, and each edit page redirects to the other when given a system of the wrong role. The provider list and defaults moved to a shared `providers.ts`.

The same pass relaid out the shared provider block: Name full width; Provider, API Key and Model on one row (one per line when narrow); Base URL on its own line; API Version, Max Tokens, Context Length and Temperature as a two-by-two grid at every width.

### 4. Chat pickers filter with the package scope
`AiChatBotController::systems()` and `ApplicationPayload::selectableSystems()` add `->role(AiSystemRole::Chat)`. `CompanyIntelSystems` already filters by role.

### 5. A dedicated `use-voice` permission
This follows the `manage-comments` precedent: a migration creates it and assigns it to no role, and it is granted from Roles & Permissions. Endpoints use `can:use-voice`.
- *Alternative: reuse `edit-resume`.* Voice also appears on persona pages and Company Intel chat, so tying it to resume editing would be arbitrary.
- *Visibility:* both `HandleInertiaRequests` and `HandleChatInertiaRequests` share a `voice` prop. It is `null` for users without the permission, so nothing renders, and `{ statusUrl, transcribeUrl, speechUrl }` otherwise. `ChatInterface` reads it from page props, so none of its three callers needs changing. Note from MEMORY: Keystone gates are off in console, so tests that check visibility must be feature tests.

### 6. Endpoints (`routes/api-web.php`, prefix `/api/admin/voice`, `auth` + `can:use-voice`)
| Method | Path | In | Out |
| --- | --- | --- | --- |
| GET | `status` | — | `{ dictation: {available, reason}, speech: {available, reason} }` |
| POST | `transcriptions` | multipart `audio`, optional `language` | `{ text }` |
| POST | `speech` | `text` (Markdown allowed), optional `voice` | `audio/mpeg` body |

- **Status** resolves each feature's default system. Reasons are `not_configured`, `inactive` or `unreachable`. It probes `GET {base_url}/models` with a 2-second timeout and caches the result for 30 seconds (cache driver `file`). Systems without a `base_url` (real cloud providers) are reported available without a probe.
- **Transcriptions** passes the `UploadedFile` straight to `TranscriptionService::forFeature('voice:dictation', …)`. PHP's temporary file is discarded at the end of the request, so no audio is retained. Validation: `mimetypes:audio/webm,audio/ogg,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav,audio/mpeg` plus `max:config('voice.max_upload_kb')`. Browsers label a MediaRecorder blob with a codec suffix (`audio/webm;codecs=opus`); the client strips it before upload so the gateway's filename mapping recognises the type.
- **Speech** converts Markdown to plain text (CommonMark to HTML, then `strip_tags`, then collapsed whitespace), checks it is non-empty and within `voice.max_speech_chars`, then reads from or writes to the cache (decision 7).
- Rate limiters `voice-transcribe` and `voice-speech` are keyed on the user id and sized for one person (for example 30/min and 120/min) in `config/voice.php`.
- Failures: any exception from the services (connection, timeout, provider HTTP error, `RuntimeException` for a wrong role) is caught in one place, logged with the system id, and rendered as `503 {"message":"Voice is unavailable."}`. This is the same "never verbatim to the browser" rule the finalize endpoints follow.

### 7. Speech cache on local disk
The key is `sha256(system_id | model | voice | plain_text)`, stored as `storage/app/voice-cache/{key}.mp3` on the `local` disk. A hit is streamed back with `Cache-Control: private, max-age=86400`. `voice:sweep-cache` deletes files older than `voice.cache_ttl_days` (default 7) and is scheduled daily next to `resume:sweep-expired-documents`.
- *Alternative: Laravel cache store.* That is the `file` driver with serialized payloads, which wastes space for binary audio and has no age-based sweep without a scan. Plain files are simpler.
- The voice is resolved before hashing (request voice, then `config.voice`, then the empty string), so changing the default voice changes the key.

### 8. Client
- **`useVoice()`** reads the shared prop, fetches status on mount (and again after a failure), and exposes `dictationAvailable`, `speechAvailable` and `reason`.
- **Dictation** (`ChatInputArea`, a new `beforeSend`-area microphone `IconButton`): `getUserMedia` → `MediaRecorder` (the browser's preferred type) → on stop, upload → insert at the cursor. States are idle, recording (with elapsed time), transcribing, then idle. Escape while recording cancels without uploading. A recording cap (`voice.max_recording_seconds`, shared through the prop) stops recording automatically.
- **Read aloud** (`ChatMessageBubble`, assistant rows only, not while streaming): a play/stop `IconButton`. A single module-level `Audio` element ensures only one clip plays at a time.
- **Proofread** (`cover-letters/Edit.tsx`, `resume/targeted/Edit.tsx`): a listen/stop button splits the editor's current Markdown into blocks (paragraphs and list items), then requests and plays them in sequence, prefetching block n+1 while n plays. Stop aborts the in-flight fetch and stops the queue.
- **Styling:** MUI icons (`Mic`, `Stop`, `VolumeUp`), no gradients or box shadows, and the disabled state carries a tooltip with the status reason. Per project practice, a frontend-design pass and screenshots happen before merge.

## Risks / Trade-offs

- [The GPU is shared with LM Studio, so a large Whisper model can push chat models out of VRAM] → Recommend medium or turbo Whisper and Speaches' idle unload. A speech request that waits for a model to load fits within laravel/ai's 30-second default timeout for short clips.
- [Long recordings exceed the 30-second transcription timeout] → Cap recording length on the client (default 120 s). Dictation clips are short by nature.
- [Status is cached for 30 s, so it can show stale availability after the tunnel drops] → A failed call returns 503, the client shows "Voice is unavailable" and immediately refetches status, which disables the controls.
- [The `openai` label misdescribes the local server] → The admin form's help text for `base_url` notes the pattern. The follow-up `supportsRole()` change in code-talker is recorded for the package owner.
- [MediaRecorder output type varies by browser (webm in Chrome/Firefox, mp4 in Safari)] → Allow both, and normalise the MIME type on the client (decision 6).
- [Unsaved editor text is sent for proofreading] → It goes only to the owner's own server and is cached locally under a hash. That is acceptable for an owner-only feature.
- [The `config` JSON is also read by chat code paths] → `config.voice` is only set on voice-role systems, which never reach chat paths.

## Migration Plan

1. Merge with the migration that creates `use-voice`. Run `php artisan migrate` and `DB_DATABASE=wink php artisan migrate` locally (both databases, per the project's practice).
2. **Developer-owned (production):** deploy as usual and run migrations. Grant `use-voice` from Roles & Permissions. Run Speaches on the LM Studio host, bound to its WireGuard address and firewalled to the tunnel. Create two AiSystems (`openai` / `transcription` and `openai` / `audio`) with `base_url`, explicit models, a placeholder key and, for audio, a default voice, then assign the `voice:dictation` and `voice:speech` defaults. Check the status endpoint from the admin pages. No queue worker restart is needed (no queued work changes), and the scheduler picks up the sweep.
3. Rollback: revert the deploy. The `use-voice` migration's `down()` removes the permission. Voice-role AiSystems can stay; they are inert without the code, and chat pickers in the reverted code would list them again, so deactivate them if rolling back.

## Resolved Questions

- **Speaches model IDs and the Kokoro voice name** are chosen at setup time. They are data on the AiSystem records (Model and Default voice), not code.
- **Transcription language: the server detects it.** `config/voice.php` carries `transcription_language`, blank by default. A request's own `language` wins; otherwise a non-blank config value is sent; otherwise no language is sent and the server auto-detects.
