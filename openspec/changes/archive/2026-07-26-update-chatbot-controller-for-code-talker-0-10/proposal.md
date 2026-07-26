## Why

`jvjvjv/code-talker` 0.10.0 restructured `ChatBotController` into container-resolved collaborator services and removed every `protected` helper the host app's `App\Http\Controllers\ChatBotController` was built on. The host controller no longer boots at all — `php artisan route:list --path=chats` currently fails with `Too few arguments to function Jvjvjv\CodeTalker\Http\Controllers\ChatBotController::__construct(), 2 passed ... exactly 10 expected` — so every `/chats`, `/chat/*`, and root-level bot route is broken.

## What Changes

- **BREAKING (already landed upstream)**: the package removed `storedState`, `putStoredState`, `storedConversation`, `historyForBot`, `routeUrlFor`, `abortIfInaccessible`, `rememberConversation`, `clearStoredState`, `requestAccessPath`, `stateKey`, and `forgetLegacyCookies` from `ChatBotController`. The host app must stop calling them.
- Retire the host controller's copies of `index`, `show`, `showByHash`, and `statuses`. The package's `ChatBotIndexPayload`, `ChatBotPagePayload`, and `ChatBotStatusResolver` now produce everything those methods produced except the host-only pieces.
- Move the host-only presentation concerns into subclasses of the package's payload services, bound in the container so the package controller picks them up:
  - **role filtering** — only bots the viewer's roles allow appear in the `/chats` index and the `/chats/statuses` response (`App\Models\AiChatBot::allowsRole()`);
  - **`allowed_roles`** prop on the `ai/ChatBot` page, consumed by `BotAccessCard.tsx`;
  - **`previousHref`** prop on the `ai/ChatBot` page, consumed by `ChatBot.tsx`.
- Reduce `App\Http\Controllers\ChatBotController` to a thin subclass that keeps the class name the published route file already points at, with no constructor and no overridden actions.
- Register the payload bindings in `AppServiceProvider::register()` alongside the existing form-request bindings.

Explicitly unchanged: `CheckChatBotAccess` middleware still enforces per-bot role authorization on the per-bot route groups, and `routes/codetalker-chatbots.php` keeps its current shape.

## Capabilities

### New Capabilities
- `host-chat-bot-presentation`: how this app customizes the CodeTalker chat-bot pages — role-filtered bot listings and statuses, and the extra `allowed_roles` / `previousHref` props on the chat page — expressed as container-swapped payload services rather than controller overrides.

### Modified Capabilities

None. `chat-component-organization` governs frontend file layout and is untouched; the React components keep receiving exactly the props they receive today.

## Impact

- **Code**: `app/Http/Controllers/ChatBotController.php` (gutted), `app/Providers/AppServiceProvider.php` (new bindings), and new host payload services under `app/Services/ChatBot/`.
- **Dependencies**: pins the host to `jvjvjv/code-talker` >= 0.10.0 service APIs (`Jvjvjv\CodeTalker\Services\ChatBot\*`). `composer.json` already tracks `dev-develop` via the `../code-talker` path repository, so no version constraint change is needed.
- **Tests**: `tests/Feature/ChatBotControllerTest.php` is the acceptance surface — its 14 tests cover guest/authenticated index filtering, conversation ordering, statuses filtering, access-path 404s, role 403s, identity capture, streaming, switch, and new-chat. They must pass unchanged; no assertion should need editing.
- **Frontend**: none. No prop names, shapes, or page components change.
- **Risk**: the package payload services query `Jvjvjv\CodeTalker\Models\AiChatBot`, which has no `allowsRole()`. Host subclasses must query `App\Models\AiChatBot` themselves rather than filtering the package's result set.
