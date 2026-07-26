# host-chat-bot-presentation

## Purpose

How this app customizes the CodeTalker chat-bot pages on top of the package defaults: role-filtered bot listings and statuses, and the extra `allowed_roles` / `previousHref` props the chat UI needs. Since code-talker 0.10.0 these customizations are expressed as container-swapped payload services rather than controller overrides.

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

### Requirement: Host customizations are applied through container-bound services

Host-specific chat-bot presentation SHALL be implemented as subclasses of `Jvjvjv\CodeTalker\Services\ChatBot\ChatBotIndexPayload`, `ChatBotPagePayload`, and `ChatBotStatusResolver`, bound in the container so the package `ChatBotController` resolves them. The host controller SHALL NOT override the package controller's actions, redeclare its constructor, or call helper methods removed in code-talker 0.10.0.

#### Scenario: Routes resolve without constructor errors

- **WHEN** `php artisan route:list --path=chats` is run
- **THEN** the chat-bot routes are listed and no `Too few arguments to function ... ChatBotController::__construct()` error occurs

#### Scenario: Removed package helpers are not referenced

- **WHEN** the host application code is searched for `storedState`, `putStoredState`, `storedConversation`, `historyForBot`, `routeUrlFor`, `abortIfInaccessible`, `rememberConversation`, `clearStoredState`, `requestAccessPath`, `stateKey`, or `forgetLegacyCookies` as calls on the chat-bot controller
- **THEN** no matches exist

#### Scenario: Package controller receives the host payload services

- **WHEN** `Jvjvjv\CodeTalker\Services\ChatBot\ChatBotPagePayload` is resolved from the container
- **THEN** the resolved instance is the host subclass

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
