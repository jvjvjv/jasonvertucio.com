## Why

code-talker 0.17 added speech-to-text and text-to-speech (`TranscriptionService`, `SpeechService`) driven by `AiSystem` records with an `audio` or `transcription` role. The app is on 0.18 with the role migration already run, but nothing uses it. The admin screens cannot create a voice system, and the chat pickers would offer one as a chat model if one existed. Voice would make two admin activities easier: dictating into the application Discussion chat, and hearing a cover letter or targeted resume read back to catch awkward prose.

Voice is for the site owner only. It runs on a local OpenAI-compatible speech server on the GPU machine that already serves LM Studio, reached over the existing WireGuard tunnel, so it costs nothing per call and no audio leaves the owner's infrastructure.

## What Changes

- AI system administration gains a **role** (`chat`, `audio`, `transcription`) chosen at creation. Provider choices are filtered to those that can serve the role, `elevenlabs` is added, and fields that only apply to chat are hidden and not required for voice roles. Feature defaults gain `voice:dictation` (transcription systems) and `voice:speech` (audio systems), and a voice system can only be the default for its own role's feature.
- Pickers that choose a chat model (persona backing system, New Session / I Applied system list) no longer list voice systems.
- A new permission, `use-voice`, gates every voice control and endpoint. It is created by migration and assigned to no role.
- **Dictation**: a microphone control in the shared chat composer records audio, transcribes it, and inserts the text into the message box without sending it.
- **Read aloud**: a play control on assistant replies speaks the reply, with Markdown reduced to plain text.
- **Proofread by ear**: a listen control on the cover letter and targeted resume edit pages reads the document's body aloud, paragraph by paragraph.
- **Voice status**: the controls show whether voice is available and are disabled when it is not (no default system, or the speech server cannot be reached), instead of failing after the owner has spoken.
- Generated speech is cached on local disk by text, voice and model, and swept daily.

## Capabilities

### New Capabilities
- `ai-system-voice-roles`: administering AiSystems with a chat/audio/transcription role, role-aware provider and field validation, voice feature defaults, and excluding voice systems from chat-model pickers.
- `admin-voice`: owner-only dictation, read-aloud and proofread-by-ear, the voice status check, the permission that gates them, failure behavior when the speech server is unreachable, and the speech cache.

### Modified Capabilities
<!-- None: existing specs do not state requirements about system roles or chat composer controls. -->

## Impact

- **Backend**: `StoreAiSystemRequest` / `UpdateAiSystemRequest`, `AiSystemController`, `AiChatBotController::systems()`, `ApplicationPayload::selectableSystems()`; new voice controller, form requests, a speech-cache service, a `voice:sweep-cache` command, `config/voice.php`, a `use-voice` permission migration, and shared Inertia props on both the admin and chat middleware.
- **Frontend**: `ai/systems/Form.tsx` (role, provider filtering, voice feature defaults); `ChatInputArea` / `ChatInterface` (microphone); assistant message bubble (play); cover letter and targeted resume edit pages (listen); a shared voice-status hook.
- **Routes**: new JSON endpoints under `/api/admin/voice` (status, transcriptions, speech).
- **Infrastructure (developer-owned)**: a Speaches (or equivalent OpenAI-compatible) server on the LM Studio GPU host, listening on its WireGuard address. Two AiSystems configured as `provider=openai` with `base_url` pointing at it. Migrations run on both databases.
- **Dependencies**: none new. Uses code-talker's existing audio services and laravel/ai's OpenAI gateway.
- **Docs**: CLAUDE.md gains a Voice section.
