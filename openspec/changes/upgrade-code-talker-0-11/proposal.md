## Why

`jvjvjv/code-talker` v0.11.0 (installed: v0.10.0) turns the package into a pure library: it no longer registers routes, ships controllers, form requests, or Inertia config. This app's entire AI surface — the public chat routes, the `/admin/ai/*` CRUD screens, and the `/chats` UI — is currently built by extending and container-binding into package controllers, form requests, and payload services that no longer exist after upgrading. The app cannot take v0.11.0's new features (replayable tool-call history via `laravel/ai`'s `ConversationStore`, the `http-request` and `get-temporal-information` tools, private-network fetch protection) without first replacing everything the package used to own.

## What Changes

- **BREAKING**: Rewrite `App\Http\Controllers\ChatBotController` from an empty subclass of the package's `ChatBotController` (removed) into a real controller driving `AiChatBotConversationService::startConversation()` / `continueConversation()` directly, streaming turns through the package's new `SseFrameEncoder`.
- **BREAKING**: Replace the package's session-cookie conversation resolution (`ConversationSessionStore`, the `ai_chat_bot_current` cookie) with host-owned resolution via `findByChatHashOrUuid()`.
- **BREAKING**: Rewrite `App\Http\Controllers\Admin\AiChatBotController` from a subclass of the package's admin controller (removed) into a controller built on the new `Services\Management\AiChatBotManager`.
- **BREAKING**: Replace `routes/codetalker-admin.php`'s direct use of the package's `AiSystemPromptController` and `AiMemoryController` (both removed) with new host controllers built on `AiSystemPromptManager` and `AiMemoryManager`.
- **BREAKING**: Replace `App\Http\Requests\Admin\{Store,Update}AiChatBotRequest` (which extend removed package base request classes) with plain `FormRequest`s using `AiChatBotManager::createRules()` / `updateRules()`.
- **BREAKING**: Replace `App\Http\Requests\{Store,Update}AiSystemRequest` (which reference package validation the removed `AiSystemController` used) with rules sourced from `AiSystemManager::createRules()` / `updateRules()`.
- **BREAKING**: Delete the now-dead container bindings in `AppServiceProvider` for `BaseChatBotIndexPayload`, `BaseChatBotPagePayload`, `BaseChatBotStatusResolver`, `BaseStoreAiChatBotRequest`, `BaseUpdateAiChatBotRequest` (all removed from the package), and delete `App\Services\ChatBot\{HostChatBotPagePayload,RoleFilteredChatBotIndexPayload,RoleFilteredChatBotStatusResolver}`, whose base classes no longer exist.
- **BREAKING**: Own route registration for every chat and admin-AI route. The package no longer loads `routes/codetalker-*.php` or publishes route tags — these files move into the app's own route loading (`routes/web.php`) unconditionally.
- Rebuild conversation history reads on the package's `Services\Conversation\CodeTalkerConversationStore` (bound over `laravel/ai`'s default `ConversationStore`), replacing the removed `TranscriptBuilder` / `ConversationTranscript`.
- Run the new `2026_08_16_000001_add_message_structure_to_ai_conversation_messages_table` migration (auto-loaded from the package, no publish needed).
- Adopt the two new MCP tools (`http-request`, `get-temporal-information`) where useful to the chat bots' tool sets, after confirming no host tool directory already registers a `http-request` tool (package doc flags this as a known collision risk).
- Audit `fetch-web-page` callers (chat bots, targeted-resume tooling) for any use against loopback/private-network hosts (e.g. local Jellyfin, LM Studio) that now needs an explicit `request_policy.allow_private_hosts` declaration.
- Confirm cancellation still works: turns driven outside an HTTP request (queued jobs, console) must supply `usingCancellationCheck()` explicitly, since `connection_aborted()` no longer applies there.
- Remove the app's remaining `inertiajs/inertia-laravel`-adjacent `code-talker.inertia` config usage if present, since the package no longer ships that block (app already owns its own Inertia pages, so this is confirmation, not new work).

## Capabilities

### New Capabilities
_None — this is an internal rewiring of AI admin/chat surfaces onto the package's new service layer, not a new user-facing capability._

### Modified Capabilities
- `host-chat-bot-presentation`: The mechanism for the role-filtered `/chats` index, `/chats/statuses`, and `bot.allowed_roles` prop changes from container-swapped payload services (`ChatBotIndexPayload`, `ChatBotPagePayload`, `ChatBotStatusResolver` bindings) to direct calls from a host-owned `ChatBotController` into `AiChatBotConversationService` and the app's own role-filtering logic. The observable behavior (which bots a viewer sees, prop shapes) must not change; how it is produced does.

## Impact

- **Composer**: bump `jvjvjv/code-talker` to `^0.11` (path repo — points at the already-upgraded local package).
- **Routes**: `routes/codetalker-admin.php`, `routes/codetalker-chatbots.php`, `routes/api-web.php`, `routes/web.php` (loading).
- **Controllers**: `app/Http/Controllers/ChatBotController.php`, `app/Http/Controllers/Admin/AiChatBotController.php`; new host controllers for AI system prompts and AI memories (currently delegated entirely to the now-removed package controllers).
- **Form Requests**: `app/Http/Requests/Admin/{Store,Update}AiChatBotRequest.php`, `app/Http/Requests/{Store,Update}AiSystemRequest.php`; new form requests for system prompts and memories.
- **Services**: `app/Services/ChatBot/HostChatBotPagePayload.php`, `RoleFilteredChatBotIndexPayload.php`, `RoleFilteredChatBotStatusResolver.php` (deleted or rewritten); `app/Providers/AppServiceProvider.php` bindings.
- **Database**: new migration from the package (`ai_conversation_messages` gains `user_id`, `agent`, `attachments`, `tool_calls`, `tool_results`, `usage`).
- **MCP tools**: `app/Services/Mcp/Tools/ChatBot/*`, `app/Services/Mcp/Tools/TargetedResume/*` — check for `http-request` naming collisions and private-host fetch usage.
- **Tests**: PHPUnit feature tests covering `/chats/*`, `/admin/ai/*` routes will need to exercise the new controllers; `openspec/changes/adopt-code-talker-frontend-contract` task 5.7 (manual targeted-resume smoke test) remains outstanding and should be re-verified after this upgrade since the underlying turn-running path changes.
