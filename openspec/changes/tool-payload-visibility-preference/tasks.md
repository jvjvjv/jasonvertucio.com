## 1. User preference column

- [ ] 1.1 Create a migration adding `show_tool_payloads` boolean to `users`, `default(false)`, with a `comment()` matching the style of the existing `allow_totp_login`/`allow_passkey_login`/`require_password` columns.
- [ ] 1.2 Add `show_tool_payloads` to `App\Models\User`'s `$fillable` and `$casts` (`'boolean'`), matching the existing preference columns.

## 2. Replace the environment gate at both call sites

- [ ] 2.1 In `app/Http/Controllers/ChatBotController.php::message()`, replace `if (! app()->environment('production')) { $this->conversationService->usingToolPayloads(); }` with a check against `$request->user()?->can('manage-ai-tools') && $request->user()->show_tool_payloads` (guard the second access — only read `show_tool_payloads` once the user/permission check has already passed).
- [ ] 2.2 In `app/Services/ChatBot/HostChatBotPagePayload.php::transcriptWithToolPanels()`, replace `$includePayloads = ! app()->environment('production');` with the equivalent check against `$this->request->user()`.

## 3. Profile settings toggle

- [ ] 3.1 Add `ProfileController::updateToolVisibility(Request $request)`: `$request->validate(['show_tool_payloads' => 'boolean'])`, authorize `manage-ai-tools` (403 if absent), `$request->user()->update(['show_tool_payloads' => $request->boolean('show_tool_payloads')])`, `back()->with('status', ...)`.
- [ ] 3.2 Register `Route::put('/profile/tool-visibility', [ProfileController::class, 'updateToolVisibility'])->name('profile.tool-visibility.update')` alongside the existing `profile.auth-preferences.update` route.
- [ ] 3.3 Add `resources/views/profile/components/tool-visibility-preference.blade.php` — a `<form>` posting to `profile.tool-visibility.update` with one `.keystone-checkbox-label` for `show_tool_payloads`, styled consistently with `auth-preferences.blade.php`.
- [ ] 3.4 In `resources/views/profile/show.blade.php`, add a new `<section>` wrapped in `@can('manage-ai-tools') ... @endcan` including the new partial.

## 4. Update the affected spec and tests

- [ ] 4.1 Rewrite `tests/Feature/HostChatBotPagePayloadTest.php::test_chat_page_redacts_tool_arguments_and_output_in_production` (rename to drop "in_production" from the name) to cover: a user with `manage-ai-tools` + `show_tool_payloads=true` sees payloads; the same user with `show_tool_payloads=false` doesn't; a user without `manage-ai-tools` (even with `show_tool_payloads=true` forced in the DB) doesn't; a guest doesn't. Drop the `$this->app->detectEnvironment(...)` call entirely — no longer the relevant variable.
- [ ] 4.2 Verify (don't just assume) `tests/Feature/ChatBotControllerTest.php`'s guest-message test still passes unchanged — its `usingToolPayloads()` mock expectation is already `zeroOrMoreTimes()`, which should tolerate a guest never triggering the call under the new gate.

## 5. Verification

- [ ] 5.1 Run `vendor/bin/phpunit` — full suite passes, including the rewritten test from 4.1.
- [ ] 5.2 Manually verify: as a user with `manage-ai-tools`, the profile page shows the toggle; enabling it and sending a message that calls a tool shows input/output live and after reload, in your actual `APP_ENV` (not just non-production); as a user without `manage-ai-tools`, no toggle appears and no tool payloads show even if `show_tool_payloads` is manually set to `true` in the database.
- [ ] 5.3 Confirm no changes were made under `/home/jasonv/Code/@jvjvjv/code-talker` for this change.
