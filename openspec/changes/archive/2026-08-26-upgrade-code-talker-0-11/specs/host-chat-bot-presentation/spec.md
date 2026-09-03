## MODIFIED Requirements

### Requirement: Host customizations are applied directly in host-owned controllers and services

Host-specific chat-bot presentation SHALL be implemented as plain host classes under `App\Services\ChatBot\` and `App\Http\Controllers\ChatBotController`, calling `Jvjvjv\CodeTalker\Services\AiChatBotConversationService` and `Jvjvjv\CodeTalker\Services\ChatBot\SseFrameEncoder` directly. There is no package controller to extend and no package payload base classes to subclass or bind in the container — code-talker 0.11.0 removes `ChatBotController`, `ChatBotIndexPayload`, `ChatBotPagePayload`, and `ConversationSessionStore` entirely. (`Jvjvjv\CodeTalker\Services\ChatBot\ChatBotStatusResolver` is the one collaborator from this family the package still ships unchanged in 0.11.0; the host's `RoleFilteredChatBotStatusResolver` may keep extending it directly, instantiated by the host controller rather than resolved through a container binding.) The host controller owns route dispatch, per-browser conversation continuity (current-conversation session state, mirrored into a single cookie), and SSE streaming outright.

#### Scenario: Routes resolve without constructor errors

- **WHEN** `php artisan route:list --path=chats` is run
- **THEN** the chat-bot routes are listed and no missing-class or unresolvable-binding error occurs for `App\Http\Controllers\ChatBotController`

#### Scenario: No references to removed package classes remain

- **WHEN** the host application code is searched for `Jvjvjv\CodeTalker\Http\Controllers\ChatBotController`, `Jvjvjv\CodeTalker\Services\ChatBot\ChatBotIndexPayload`, `ChatBotPagePayload`, `ChatBotAccessGuard`, `ChatBotRouteUrls`, `ConversationHistoryPresenter`, `ConversationSessionStore`, or `ChatStreamResponse`
- **THEN** no matches exist outside this change's own documentation

#### Scenario: Container bindings for removed base classes are gone

- **WHEN** `app/Providers/AppServiceProvider.php` is inspected
- **THEN** it contains no `$this->app->bind(...)` call whose key is a class removed from the package in 0.11.0

## ADDED Requirements

### Requirement: Per-browser conversation continuity survives the upgrade

Anonymous and authenticated visitors SHALL retain the ability to have multiple past conversations per bot on one browser, with `message()` continuing the browser's current conversation for that bot when no explicit conversation is targeted, and `switch()` moving the browser's current conversation to a prior one for that bot. This state SHALL be stored server-side in the session, per bot, mirrored into a single cookie holding only the current conversation's identifier — reproducing the behavior code-talker 0.10.0's `ConversationSessionStore` provided before its removal in 0.11.0, not a session-per-request or URL-hash-only scheme.

#### Scenario: A second message without a hash continues the same conversation

- **WHEN** a visitor sends a first message to a bot with no conversation hash in the request, then sends a second message the same way
- **THEN** both messages persist to the same `AiConversation`

#### Scenario: Switching changes the browser's current conversation for that bot only

- **WHEN** a visitor with two prior conversations for a bot calls `switch()` naming the older one
- **THEN** the browser's session state for that bot now points at the older conversation, and state for any other bot is unaffected

#### Scenario: Legacy per-bot cookies are cleared on sight

- **WHEN** a request arrives carrying a cookie matching the pre-0.10.0 per-bot pattern (`ai_chat_bot_conversations_{id}`)
- **THEN** the response clears that cookie
