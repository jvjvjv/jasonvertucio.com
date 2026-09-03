## MODIFIED Requirements

### Requirement: Chat-bot index lists only bots the viewer's permission allows

The `/chats` index SHALL list every active chat bot the viewer is permitted to open, and SHALL omit bots whose `required_permission` the viewer does not hold. A bot with no `required_permission` SHALL be treated as public. `required_permission` MAY also be the literal value `"authenticated"`, meaning any signed-in user regardless of permissions (`AiChatBot::PERMISSION_AUTHENTICATED`). Permission evaluation SHALL use `App\Models\AiChatBot::allowsAccess()`.

#### Scenario: Guest sees only public active bots

- **WHEN** a guest requests `/chats` and the site has an active public bot, an active bot restricted to the `manage-ai-tools` permission, and an inactive public bot
- **THEN** the `ai/ChatBotsIndex` page receives exactly one bot, the active public one, with an empty `conversations` array

#### Scenario: Authenticated user sees bots matching their permissions

- **WHEN** a user holding the `manage-ai-tools` permission requests `/chats` and the site has an active public bot, an active bot restricted to `manage-ai-tools`, and an active bot restricted to a permission the user lacks
- **THEN** the page receives exactly two bots — the `manage-ai-tools`-restricted one and the public one — and not the bot restricted to the permission the user lacks

#### Scenario: Any signed-in user sees bots restricted to "authenticated"

- **WHEN** any authenticated user (regardless of permissions) requests `/chats` and the site has a bot restricted to `"authenticated"`
- **THEN** that bot is included in the page's `bots`

#### Scenario: Guests never see bots restricted to "authenticated"

- **WHEN** a guest requests `/chats` and the site has a bot restricted to `"authenticated"`
- **THEN** that bot is omitted from the page's `bots`

#### Scenario: Bots are ordered by name and conversations by recency

- **WHEN** an authenticated user requests `/chats` and one of their visible bots has two of their conversations with different last-message times
- **THEN** bots appear ordered by name and each bot's `conversations` are ordered most-recently-active first

#### Scenario: Guests get no conversation list

- **WHEN** a guest requests `/chats`
- **THEN** every listed bot's `conversations` array is empty

### Requirement: Chat-bot statuses respond only for permitted bots

The `/chats/statuses` endpoint SHALL return readiness statuses keyed by bot slug for active bots the viewer's permission allows, and SHALL omit bots the viewer cannot access. Bots sharing an `AiSystem` SHALL be checked at most once per request.

#### Scenario: Statuses are filtered by permission

- **WHEN** a viewer requests `/chats/statuses` and the site has bots they can and cannot access
- **THEN** the JSON `statuses` object contains a key only for each accessible bot's slug

#### Scenario: Shared systems are checked once

- **WHEN** two accessible bots reference the same `AiSystem`
- **THEN** readiness is resolved once for that system and reused for both slugs

### Requirement: Chat page carries the bot's required permission

The `ai/ChatBot` page props SHALL include a `required_permission` string on the `bot` object, defaulting to `null` when the bot has none, so `BotAccessCard` can label the bot's access level.

#### Scenario: Restricted bot exposes its permission

- **WHEN** the chat page renders for a bot whose `required_permission` is `"manage-ai-tools"`
- **THEN** the page props contain `bot.required_permission` equal to `"manage-ai-tools"`

#### Scenario: Public bot exposes null

- **WHEN** the chat page renders for a bot with no `required_permission`
- **THEN** the page props contain `bot.required_permission` equal to `null`

### Requirement: Existing chat-bot behavior is preserved

Behavior the package already owns SHALL continue to work unchanged after the host controller is reduced: access-path enforcement, per-bot permission authorization via `CheckChatBotAccess`, visitor identity capture, message streaming, conversation switching, hash-link loading, and new-chat resets.

#### Scenario: Wrong entry point still 404s

- **WHEN** a bot configured for the root access path is requested at `/chat/{slug}`
- **THEN** the response is 404

#### Scenario: Permission-restricted bot still 403s for guests

- **WHEN** a guest requests a bot whose `required_permission` is set
- **THEN** the response is 403

#### Scenario: Hash link restores the conversation

- **WHEN** `/chat/{slug}/{hash}` is requested for an existing conversation
- **THEN** the conversation becomes the current one in session and the page renders its messages, `chatHash`, and `previousHref`

#### Scenario: The feature test suite passes with permission-based fixtures

- **WHEN** `tests/Feature/ChatBotControllerTest.php` is run
- **THEN** all of its tests pass, with scenarios previously restricting bots by role now restricting them by `required_permission`
