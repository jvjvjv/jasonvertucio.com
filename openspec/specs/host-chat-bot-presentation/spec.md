# host-chat-bot-presentation

## Purpose

How this app customizes the CodeTalker chat-bot pages on top of the package defaults: role-filtered bot listings and statuses, and the extra `allowed_roles` / `previousHref` props the chat UI needs. As of code-talker 0.11.0, the package no longer ships a controller or payload base classes to extend or bind — these customizations, plus per-browser conversation continuity, are implemented directly in host-owned controllers and services.

## Requirements

### Requirement: Chat-bot index lists only bots the viewer's roles permit

The `/chats` index SHALL list every active chat bot the viewer is permitted to open, and SHALL omit bots whose `allowed_roles` the viewer does not satisfy. A bot with an empty `allowed_roles` SHALL be treated as public. Role evaluation SHALL use `App\Models\AiChatBot::allowsRole()`.

#### Scenario: Guest sees only public active bots

- **WHEN** a guest requests `/chats` and the site has an active public bot, an active bot restricted to `admin`, and an inactive public bot
- **THEN** the `ai/ChatBotsIndex` page receives exactly one bot, the active public one, with an empty `conversations` array

#### Scenario: Authenticated user sees bots matching their roles

- **WHEN** a user holding the `editor` role requests `/chats` and the site has an active public bot, an active bot restricted to `editor`, and an active bot restricted to `admin`
- **THEN** the page receives exactly two bots — the `editor`-restricted one and the public one — and not the `admin`-restricted one

#### Scenario: Bots are ordered by name and conversations by recency

- **WHEN** an authenticated user requests `/chats` and one of their visible bots has two of their conversations with different last-message times
- **THEN** bots appear ordered by name and each bot's `conversations` are ordered most-recently-active first

#### Scenario: Guests get no conversation list

- **WHEN** a guest requests `/chats`
- **THEN** every listed bot's `conversations` array is empty

### Requirement: Chat-bot statuses respond only for permitted bots

The `/chats/statuses` endpoint SHALL return readiness statuses keyed by bot slug for active bots the viewer's roles permit, and SHALL omit bots the viewer cannot access. Bots sharing an `AiSystem` SHALL be checked at most once per request.

#### Scenario: Statuses are filtered by role

- **WHEN** a viewer requests `/chats/statuses` and the site has bots they can and cannot access
- **THEN** the JSON `statuses` object contains a key only for each accessible bot's slug

#### Scenario: Shared systems are checked once

- **WHEN** two accessible bots reference the same `AiSystem`
- **THEN** readiness is resolved once for that system and reused for both slugs

### Requirement: Chat page carries the bot's allowed roles

The `ai/ChatBot` page props SHALL include an `allowed_roles` array on the `bot` object, defaulting to an empty array when the bot has none, so `BotAccessCard` can label the bot's access level.

#### Scenario: Restricted bot exposes its roles

- **WHEN** the chat page renders for a bot whose `allowed_roles` is `["editor"]`
- **THEN** the page props contain `bot.allowed_roles` equal to `["editor"]`

#### Scenario: Public bot exposes an empty array

- **WHEN** the chat page renders for a bot with no `allowed_roles`
- **THEN** the page props contain `bot.allowed_roles` equal to `[]`

### Requirement: Chat page carries a same-host back link

The `ai/ChatBot` page props SHALL include `previousHref`, resolved from the request's `Referer` header when that referer points at this host and differs from the current URL, and falling back to the `chat-bots.index` route otherwise.

#### Scenario: Same-host referer is preserved

- **WHEN** the chat page is requested with a `Referer` on this host that is not the current URL
- **THEN** `previousHref` equals that referer

#### Scenario: Missing, self, or off-host referer falls back to the index

- **WHEN** the chat page is requested with no `Referer`, with a `Referer` equal to the current URL, or with a `Referer` on another host
- **THEN** `previousHref` equals the `chat-bots.index` route URL

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

### Requirement: Existing chat-bot behavior is preserved

Behavior the package already owns SHALL continue to work unchanged after the host controller is reduced: access-path enforcement, per-bot role authorization via `CheckChatBotAccess`, visitor identity capture, message streaming, conversation switching, hash-link loading, and new-chat resets.

#### Scenario: Wrong entry point still 404s

- **WHEN** a bot configured for the root access path is requested at `/chat/{slug}`
- **THEN** the response is 404

#### Scenario: Role-restricted bot still 403s for guests

- **WHEN** a guest requests a bot whose `allowed_roles` is non-empty
- **THEN** the response is 403

#### Scenario: Hash link restores the conversation

- **WHEN** `/chat/{slug}/{hash}` is requested for an existing conversation
- **THEN** the conversation becomes the current one in session and the page renders its messages, `chatHash`, and `previousHref`

#### Scenario: The existing feature test suite passes unedited

- **WHEN** `tests/Feature/ChatBotControllerTest.php` is run
- **THEN** all of its tests pass with no assertion changes

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
