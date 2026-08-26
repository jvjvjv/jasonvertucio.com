## Context

Code Talker 0.10.0 (installed) still owned the HTTP layer: `ChatBotController`, the admin CRUD controllers, form requests, and route files lived in the package, and this app customized behavior by subclassing controllers and container-binding replacement collaborators (`ChatBotIndexPayload`, `ChatBotPagePayload`, `ChatBotStatusResolver`, form request base classes). 0.11.0 deletes all of that. The package is now a service layer only: `AiChatBotConversationService` (turn lifecycle), `SseFrameEncoder` (wire framing), `Services\Management\*` (five managers replacing the admin controllers' logic), and `Services\Conversation\CodeTalkerConversationStore` (history storage, bound over `laravel/ai`'s `ConversationStore`).

This app currently has ten routes across `routes/codetalker-chatbots.php` (public chat) and `routes/codetalker-admin.php` + `routes/api-web.php` (admin), all pointing at controllers that either extend a now-deleted package controller or import a now-deleted package controller directly (`AiSystemPromptController`, `AiMemoryController`). Every one of these needs a from-scratch host controller.

## Goals / Non-Goals

**Goals:**
- Every existing route (`/chats`, `/chats/statuses`, `/chat/{slug}/{hash}`, `/{aiChatBot:slug}` + `new`/`status`/`warmup`/`messages`/`reset`/`switch`, and the full `/admin/ai/*` + `/api/admin/ai/*` surface) keeps its current URL, method, and response shape.
- No frontend (React/Inertia) changes beyond what `adopt-code-talker-frontend-contract` already covers — this change is server-side only.
- Role-filtered bot visibility (`host-chat-bot-presentation` spec) preserved with identical scenarios, implemented as plain host logic instead of container-swapped services.
- Conversation history continues to read correctly through the new `CodeTalkerConversationStore`-backed replay.

**Non-Goals:**
- Adopting `http-request` / `get-temporal-information` tools into any specific bot's toolset — that's a follow-up content decision, not part of this upgrade's mechanics. This change only clears the naming-collision risk and leaves the tools available.
- Migrating `TargetedResumeService` onto `ConversationTurnRunner` (already tracked as a separate follow-up in `adopt-code-talker-frontend-contract` task 6.2).
- Redesigning the admin CRUD UI/UX. New host controllers reproduce the removed package controllers' behavior; they don't change it.

## Decisions

### Host `ChatBotController` is a full rewrite against `AiChatBotConversationService`, not a thin wrapper
The removed package controller did: session/cookie resolution → guard checks → payload assembly → SSE streaming. All of that logic now needs a home in the host controller directly, since there is no base class to inherit it from.

- **`index()` / `statuses()`**: currently produced by `RoleFilteredChatBotIndexPayload` / `RoleFilteredChatBotStatusResolver`, which wrapped package base classes purely to inject role filtering. Since those base classes are gone, fold the role-filtering logic directly into the host controller (or keep it as plain, un-based service classes called directly — no container swap needed since there's nothing to swap in for).
- **`show()` / `newChat()` / `showByHash()`**: replace `ChatBotPagePayload` (removed) with a small host-owned payload builder that assembles the existing Inertia props (`ChatBotPageProps` fields per `resources/js/types/code-talker.d.ts`) from `AiConversation`/`AiChatBot` models directly, plus the host's `allowed_roles`/`previousHref` additions already tracked in `host-chat-bot-presentation`.
- **`message()`**: call `AiChatBotConversationService::startConversation()` (if no conversation resolved yet) or reuse the existing one resolved via `findByChatHashOrUuid()`, then stream `continueConversation()` through `SseFrameEncoder::encode()` as the HTTP response body (`StreamedResponse` with `Content-Type: text/event-stream`), preserving the documented `X-Chat-Hash` response header the frontend contract depends on.
- **`reset()` / `switch()`**: confirmed against the removed package's v0.10.0 source (`ChatBotController::message()`/`switch()`/`newChat()`) that per-browser conversation continuity (which conversation `message()` continues when no hash is in the URL, and `switch()`'s ability to jump to a prior conversation for the same bot) was driven by `ConversationSessionStore`'s per-bot session/cookie entry, not the URL alone. This is real anonymous-visitor UX (multiple past conversations per bot, no login required), not just an implementation detail — losing it would be a regression. Reimplement a minimal host-owned equivalent: a small session-backed store (bot slug → current conversation UUID) that `message()`, `switch()`, and `newChat()`/`reset()` read and write, mirroring the removed class's contract (`currentConversation()`, `remember()`, `switchTo()`, `startNewChat()`) but owned in `app/Services/ChatBot/`.
- **`status()` / `warmup()`**: unaffected by the removal — these hit `AiModelReadinessService`/`ProviderModelsClient`, which are untouched library classes. Just re-wire the host controller to call them directly instead of inheriting the call.

### Cancellation must be explicit
`continueConversation()`'s default cancellation check reads `connection_aborted()`. That's correct for the synchronous HTTP streaming path (`message()` running inline in a request), so **no explicit `usingCancellationCheck()` call is needed for the chat-bot HTTP flow** — only for `TargetedResumeService`'s usage, which already runs its own turn loop outside this controller and is out of scope here (tracked separately).

### Admin controllers rebuild on `Services\Management\*`, keeping the app's Inertia pages untouched
Each manager's `createRules()`/`updateRules()` (or `rules()` for prompts) becomes the new form request's `rules()` body, replacing inheritance from a removed base request class. Controller methods become thin: validate → call manager method → return the same Inertia response/redirect the removed base controller used to produce. `AiChatBotManager`, `AiSystemManager`, `AiMemoryManager`, `AiConversationManager`, `AiSystemPromptManager` cover every admin operation the removed controllers performed (confirmed against each manager's public API), so no new package-level surface is missing.

`App\Http\Controllers\Admin\AiChatBotController` already overrides most methods (only `destroy()`/`mcpTools()` were inherited) — those two gain explicit bodies calling `AiChatBotManager::delete()` / the tool-listing manager method.

`AiSystemPromptController` and `AiMemoryController` have no host override today (routes call the package classes directly) — these are net-new host controllers, built the same way as `AiChatBotController`'s existing pattern (Inertia pages + `ProvidesAdminNavigation`), sized to whatever the package controllers did (CRUD + duplicate/rebuild-memories actions).

### Route ownership moves fully into the app
Since the package no longer conditionally loads `routes/codetalker-*.php` from its `booted()` callback, these files stop being package-recognized overrides and become ordinary app route files, required unconditionally from `routes/web.php` (matching how every other route group in this app is loaded). No functional change to route structure — only to how the file gets included.

### `AppServiceProvider` cleanup
Remove the five bindings keyed on now-deleted package classes (`BaseChatBotIndexPayload`, `BaseChatBotPagePayload`, `BaseChatBotStatusResolver`, `BaseStoreAiChatBotRequest`, `BaseUpdateAiChatBotRequest`). Keep `Route::model('aiChatBot', ...)`, `addToolDirectory()`, and `registerToolParameterResolver()` — those bind against library surface that's unchanged in 0.11.0.

### Migration and `ConversationStore` binding are automatic
The package auto-loads its own migrations (`loadMigrationsFrom`), so `2026_08_16_000001_add_message_structure_to_ai_conversation_messages_table` runs on `php artisan migrate` with no publish step. `CodeTalkerConversationStore` replaces the framework's default `ConversationStore` binding inside the package's own service provider — no app-side binding change needed, only running the migration before any conversation is read/written under the new schema.

## Risks / Trade-offs

- **[Risk]** The host-owned `message()` streaming implementation could drift from the exact SSE framing the frontend's `useChatStream` hook expects (blank-line splitting, `[DONE]` terminator, error-is-terminal semantics) → **Mitigation**: use `SseFrameEncoder::encode()` verbatim rather than hand-rolling frame joins; it's the package's own encoder and matches the documented contract exactly.
- **[Risk]** Losing a guard that used to live inside the removed controller (e.g., an access check, a cookie-clear side effect) since there's no diff against a base class to review → **Mitigation**: `AiChatBotConversationService`'s doc comments explicitly call out which guards moved into the service (inactive bot, missing visitor identity) versus which remain the controller's job; treat anything not mentioned there as a controller responsibility to re-derive from the 0.10.0 package source (still available via `git show v0.10.0:src/Http/Controllers/ChatBotController.php` in the package repo) before deleting the app's current controller.
- **[Risk]** `reset()`/`switch()` behavior changes subtly once the session/cookie resolution is gone (e.g., a user without a hash in the URL previously got their last-active conversation from a cookie; now they won't) → **Mitigation**: check `resources/js/hooks/useChatStream.ts` and the chat pages for any reliance on cookie-based conversation continuity before finalizing the resolution strategy; if the frontend already always carries the hash (per the 0.10.0 contract work), this is a no-op in practice.
- **[Risk]** New migration touches a live, populated `ai_conversation_messages` table → **Mitigation**: all new columns are nullable/JSON additions per the changelog ("adds `user_id`, `agent`, `attachments`, `tool_calls`, `tool_results`, and `usage`"), so this is additive and backward-compatible; verify with `php artisan migrate --pretend` before running against production data.
- **[Trade-off]** This change intentionally does not touch `TargetedResumeService`'s separate turn-running path, so the app will run two different patterns (host `ChatBotController` on `AiChatBotConversationService`, `TargetedResumeService` on its own loop) until the tracked follow-up migrates it. Acceptable since that follow-up is explicitly blocked on a different upstream change (`tool_use_progress`/`page_reload` as first-class events).

## Migration Plan

1. Bump `jvjvjv/code-talker` to `^0.11` in `composer.json`, run `composer update jvjvjv/code-talker` (path repo, so this picks up the local package's `v0.11.0` tag).
2. Run `php artisan migrate` for the new column additions.
3. Delete the dead `AppServiceProvider` bindings and the three `App\Services\ChatBot\*` payload classes.
4. Rewrite `ChatBotController` (public chat) against `AiChatBotConversationService` + `SseFrameEncoder`.
5. Rewrite `Admin\AiChatBotController`'s `destroy()`/`mcpTools()` against `AiChatBotManager`.
6. Add new `Admin\AiSystemPromptController` and `Admin\AiMemoryController` against their managers; update `routes/codetalker-admin.php` and `routes/api-web.php` to reference them instead of the package classes.
7. Rewrite `StoreAiSystemRequest`/`UpdateAiSystemRequest` and the two `AiChatBot` form requests to source rules from the managers.
8. Move `routes/codetalker-chatbots.php` and `routes/codetalker-admin.php` into unconditional `require` calls from `routes/web.php` (they may already be required there for chat-bots given the existing "published override" comment — verify during implementation).
9. Run the full PHPUnit suite (`vendor/bin/phpunit`), focusing on any feature tests covering `/chats/*` and `/admin/ai/*`.
10. Manually smoke-test: open `/chats`, start a conversation, send a message and confirm streaming + the `X-Chat-Hash` header, reload via the hash URL, and exercise one admin CRUD screen per resource type.

No rollback beyond `composer.json`/migration revert is needed since this is a path-repo dependency bump within the same monorepo-adjacent workflow the project already uses — the previous app code is recoverable from git history if the upgrade needs to be reverted.

## Open Questions

None outstanding. Resolved during design:

- **Session store mechanics**: confirmed against the removed package's v0.10.0 source (`Services/ChatBot/ConversationSessionStore.php`) that state lives in Laravel's server-side session, keyed per bot (`stateKey($aiChatBot)`), holding `{current: ?public_id, history: [{handle, public_id}, ...]}` capped at 25 entries. A single cookie (`ai_chat_bot_current`, 180-day expiry) mirrors only the current conversation id — a deliberate replacement for older unbounded per-bot cookies. `forgetLegacyCookies()` clears any lingering `ai_chat_bot_conversations_{id}` cookies from that older scheme; carry that cleanup forward since real visitor browsers may still hold them. The host reimplementation should reproduce this shape (session-backed state, single mirrored cookie, legacy-cookie cleanup) rather than invent a new mechanism.
- **Admin page reuse**: `Admin\AiSystemPromptController`/`Admin\AiMemoryController` reuse the existing Inertia page paths (`admin/pages/ai/system-prompts/*`, `admin/pages/ai/memories/*` already exist in `resources/js/`) and should follow `Admin\AiChatBotController`'s existing pattern (`ProvidesAdminNavigation`, redirect-with-flash on write) rather than introduce a new controller style.
