## Why

A signed-in user can't start a conversation with a persona that has `require_visitor_identity` enabled. The page correctly hides the name/email form for signed-in users (`showIdentityForm` is `false`), and `ChatBotController::startConversation()` correctly skips validating `name`/`email`. But it then calls the package's `AiPersonaConversationService::startConversation()` with `visitorName`/`visitorEmail` set to `null`. Since code-talker 0.11.0 that service enforces the identity guard itself (`vendor/jvjvjv/code-talker/src/Services/AiPersonaConversationService.php:93`). It throws `RuntimeException("The persona '…' requires a visitor name and email.")` whenever either value is blank, and it doesn't check whether a user was passed. So the signed-in user's first message fails with a 500. The persona also never learns who it's talking to, because the system prompt is built from those same null values.

A signed-in user has already identified themselves. Their account's name and email are what the persona should receive, and they shouldn't be asked to type them in.

## What Changes

- When a signed-in user opens a new conversation with an identity-requiring persona, the host supplies the account's name and email as the conversation's visitor identity. The package guard is satisfied, the conversation records `visitor_name`/`visitor_email`, and the persona's system prompt carries them.
- For those conversations, the signed-in user's account identity is authoritative. Any `name`/`email` fields in the request are ignored and are not validated.
- Guest behavior is unchanged. Guests still see the identity form and must submit a valid `name` and `email` (422 otherwise).
- Personas that don't require identity are unchanged. Signed-in users' conversations with them still carry no visitor name/email.
- New feature tests cover the signed-in path. Today the only identity test covers guests, and the one signed-in-adjacent message test mocks the service, which hides the throw.

## Capabilities

### New Capabilities

_None._

### Modified Capabilities

- `host-chat-bot-presentation`: adds a requirement that signed-in users are never asked for a visitor identity, and that identity-requiring personas receive the signed-in account's name and email instead.

## Impact

- **Code**: `app/Http/Controllers/ChatBotController.php` (`startConversation()`), and optionally a small helper if the identity resolution is extracted.
- **Tests**: `tests/Feature/ChatBotControllerTest.php`.
- **Package**: none. `vendor/jvjvjv/code-talker` is not edited. A follow-up for the package maintainer (letting the guard accept an authenticated user) is noted in design.md but isn't required for this fix.
- **Frontend**: none. `ChatBot.tsx` already hides the form and omits `name`/`email` from the payload for signed-in users.
- **Data**: new signed-in conversations with identity-requiring personas will have `visitor_name`/`visitor_email` populated from the account. Existing rows are untouched.
