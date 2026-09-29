## 1. Failing tests first

- [x] 1.1 In `tests/Feature/ChatBotControllerTest.php`, add a helper that binds a partial mock of `AiPersonaConversationService`. Build it with its real constructor dependencies (`Mockery::mock(AiPersonaConversationService::class, [app(AgentFactory::class), …])->makePartial()`), so `startConversation()` runs for real, including the package's identity guard. Only `continueConversation()` (returning `fakeStream()`) and `usingToolPayloads()` are stubbed. A plain `partialMock()` skips the constructor and leaves the service's private collaborators uninitialized, so don't use it. Verify: the helper compiles and the existing tests still pass.
- [x] 1.2 Add `test_signed_in_user_first_message_to_identity_bot_succeeds_without_name_or_email`. Set up a bot with `require_visitor_identity => true`, `actingAs($user)`, and post only `message`. Assert 200, and assert one new `AiConversation` with `user_id = $user->id`, `visitor_name = $user->name`, `visitor_email = $user->email`, and a `system` message whose content contains both. Verify: the test fails today with the package's `RuntimeException` (500).
- [x] 1.3 Add `test_signed_in_user_submitted_identity_is_ignored`. Post `name => 'Someone Else'` and `email => 'not-an-email'` as a signed-in user. Assert no 422, and assert that the conversation's visitor name/email are the account's. Verify: it fails today.
- [x] 1.4 Add `test_signed_in_user_with_blank_name_falls_back_to_email`: an account with an empty `name` gets `visitor_name = $user->email`. Verify: it fails today.
- [x] 1.5 Add `test_signed_in_user_on_identity_bot_is_not_shown_identity_form`. GET `chat-bots.chat.show` as a signed-in user for an identity bot and assert the `showIdentityForm` prop is `false`. Verify: it passes today, and it's a regression guard.
- [x] 1.6 Add `test_signed_in_user_on_non_identity_bot_gets_no_visitor_identity`. Assert the conversation is linked to the user and `visitor_name`/`visitor_email` are null. Verify: it passes today, and it's a regression guard.

## 2. Fix

- [x] 2.1 In `app/Http/Controllers/ChatBotController.php::startConversation()`, resolve the visitor identity before calling the package. For an identity-requiring bot with a signed-in user, use `$user->name` (falling back to `$user->email` when blank) and `$user->email`, and don't read or validate the request fields. For a guest, validate and use the submitted values as today. For other bots, keep the current pass-through. Update the method's docblock to say so. Verify: tests 1.2–1.4 now pass.
- [x] 2.2 Run `php artisan test --compact tests/Feature/ChatBotControllerTest.php`. Verify: every test passes, including the existing `test_first_guest_message_requires_identity_when_configured` (guests still get 422).

## 3. Wrap-up

- [x] 3.1 Draft a short note for the package maintainer describing the optional code-talker follow-up from design.md (Open Questions) — the guard could accept `$user` — without editing `vendor/` or the code-talker repo. Verify: the note is handed to the developer in the apply summary.
- [x] 3.2 Ask the developer whether to run the full test suite. Verify: it's run, or explicitly declined.
