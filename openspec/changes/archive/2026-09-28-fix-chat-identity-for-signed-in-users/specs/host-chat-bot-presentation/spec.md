## ADDED Requirements

### Requirement: Signed-in users are never asked for a visitor identity

For a chat bot with `require_visitor_identity` enabled, a signed-in user SHALL NOT be shown the visitor identity form and SHALL NOT be required to submit a name or email. Their first message SHALL open a conversation whose visitor name and email are taken from the signed-in account. Any `name` or `email` values in that request SHALL be ignored. The persona SHALL receive the account's name and email as the visitor's identity, the same way it receives a guest's submitted identity. Guests SHALL still be required to submit a valid name and email before their first message opens a conversation. For a chat bot without `require_visitor_identity`, no visitor name or email SHALL be attached to a signed-in user's conversation.

#### Scenario: Chat page hides the identity form for a signed-in user

- **WHEN** a signed-in user opens the chat page of a bot that requires visitor identity and has no current conversation
- **THEN** the page props contain `showIdentityForm` equal to `false`

#### Scenario: Signed-in user's first message succeeds without name or email

- **WHEN** a signed-in user sends a first message, with no `name` or `email` fields, to a bot that requires visitor identity
- **THEN** the response streams successfully (not a 422 and not a 500), and a new conversation is created and linked to that user

#### Scenario: Conversation carries the account's identity

- **WHEN** a signed-in user named "Ada Lovelace" with email `ada@example.com` opens a conversation with a bot that requires visitor identity
- **THEN** the conversation's visitor name is "Ada Lovelace", its visitor email is `ada@example.com`, and the persona's system prompt for that conversation includes both

#### Scenario: Submitted identity fields are ignored for signed-in users

- **WHEN** a signed-in user's first message to a bot that requires visitor identity includes `name` = "Someone Else" and an invalid `email` value
- **THEN** no validation error is returned, and the conversation's visitor name and email are the account's own, not the submitted values

#### Scenario: Guests still must identify themselves

- **WHEN** a guest sends a first message without `name` and `email` to a bot that requires visitor identity
- **THEN** the response is 422 with validation errors for `name` and `email`

#### Scenario: Bots without the identity requirement attach no identity

- **WHEN** a signed-in user opens a conversation with a bot that does not require visitor identity
- **THEN** the conversation is linked to the user and its visitor name and email are empty
