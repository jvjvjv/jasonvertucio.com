## Context

See proposal.md (Why) for the failure. The relevant pieces as they are today:

- `ChatBotController::show()` / `showByHash()` already set `showIdentityForm` to `! $request->user() && $bot->require_visitor_identity && …`. The page side is correct.
- `resources/js/chat/pages/ai/ChatBot.tsx` sends `{ name, email }` only while `showIdentityForm` is true, so a signed-in user's request carries neither field. The frontend is also correct.
- `ChatBotController::startConversation()` skips validation for signed-in users, then passes `visitorName: $request->string('name') ?: null` and `visitorEmail: …` to the package. For a signed-in user both are `null`.
- `AiPersonaConversationService::startConversation()` (package, code-talker ≥ 0.11.0) throws when the persona requires identity and either value is blank. It ignores the `$user` argument when checking. It then builds the system prompt from the same `visitorName`/`visitorEmail`.
- The existing `test_message_endpoint_creates_session_conversation_and_streams` mocks `AiPersonaConversationService` entirely. That is why the package guard's effect on signed-in users was never caught.

Constraint: `vendor/jvjvjv/code-talker` must not be edited from this repo. The fix has to live in the host.

## Goals / Non-Goals

**Goals:**
- A signed-in user can chat with an identity-requiring persona with no form and no error.
- The persona receives the account's name and email as the visitor identity.

**Non-Goals:**
- Changing the package's guard. That's a possible follow-up, see below.
- Attaching account identity to conversations with personas that *don't* require it. That would change what public personas' system prompts disclose, so it's a separate decision.
- Backfilling `visitor_name`/`visitor_email` on existing conversations.
- Handling a user who signs in or out *mid-conversation*. Identity is resolved once, when the conversation is opened, same as for guests.

## Decisions

**1. Resolve identity in the host controller, from the authenticated user.**
In `startConversation()`, when the persona requires identity:
- signed-in user: `visitorName = $user->name`, `visitorEmail = $user->email`. The request's `name`/`email` are neither validated nor read.
- guest: validate as today and take the submitted values.

When the persona doesn't require identity, keep passing whatever the request carries, which is `null` in practice. That leaves current behavior unchanged for those personas.

*Alternative considered:* have the frontend prefill `name`/`email` from `page.props.auth.user` and keep sending them. Rejected. It trusts client-supplied identity for an authenticated user, and it lets a signed-in user impersonate someone else in the transcript.

*Alternative considered:* subclass or wrap `AiPersonaConversationService` to relax the guard when `$user` is set. Rejected. It fights a guard the package deliberately moved into the service, and the persona still wouldn't get the name/email for its prompt.

**2. The account's identity is authoritative for signed-in users.**
Submitted `name`/`email` are ignored rather than preferred. That keeps the transcript attribution tied to the authenticated account, and it matches the page, which never shows those fields to a signed-in user.

**3. Fallback for an account with a blank name.**
`users.name` is expected to be populated, but the package guard throws on *any* blank value. If the account name is blank, use the account email as the visitor name, so a signed-in user can never hit the 500 this change removes. Email is always present on `User`.

## Risks / Trade-offs

- [The persona's system prompt now contains the signed-in user's real email for identity-requiring personas] → That's the same exposure a guest opts into by typing it. It's scoped to personas the site owner explicitly configured to require identity.
- [The package later changes the guard's semantics, e.g. to accept `$user`] → Host behavior stays correct either way. Passing explicit identity satisfies both old and new guards.
- [The controller test that mocks the whole service keeps hiding package-level guards] → The new tests run against the real `AiPersonaConversationService::startConversation()`, and mock only the streaming step (`continueConversation`), so the guard is exercised.

## Migration Plan

No migration or data change. The deploy is the normal code deploy. Rollback is reverting the controller change.

## Open Questions

- Package follow-up (for the package maintainer, not this change): should `AiPersonaConversationService::startConversation()` treat a passed `$user` as satisfying `require_visitor_identity` and derive the name/email itself? Worth raising in `jvjvjv/code-talker`. This change doesn't depend on it.
