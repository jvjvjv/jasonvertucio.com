## 1. User preference column

- [x] 1.1 Create a migration adding `show_tool_payloads` boolean to `users`, `default(false)`, with a `comment()` matching the style of the existing `allow_totp_login`/`allow_passkey_login`/`require_password` columns.
- [x] 1.2 Add `show_tool_payloads` to `App\Models\User`'s `$fillable` and `$casts` (`'boolean'`), matching the existing preference columns.

## 2. Replace the environment gate at both call sites

- [x] 2.1 In `app/Http/Controllers/ChatBotController.php::message()`, replace `if (! app()->environment('production')) { $this->conversationService->usingToolPayloads(); }` with a check against `$request->user()?->can('manage-ai-tools') && $request->user()->show_tool_payloads` (guard the second access — only read `show_tool_payloads` once the user/permission check has already passed).
- [x] 2.2 In `app/Services/ChatBot/HostChatBotPagePayload.php::transcriptWithToolPanels()`, replace `$includePayloads = ! app()->environment('production');` with the equivalent check against `$this->request->user()`.

## 3. Profile settings toggle

- [x] 3.1 Add `ProfileController::updateToolVisibility(Request $request)`: `$request->validate(['show_tool_payloads' => 'boolean'])`, authorize `manage-ai-tools` (403 if absent), `$request->user()->update(['show_tool_payloads' => $request->boolean('show_tool_payloads')])`, `back()->with('status', ...)`.
- [x] 3.2 Register `Route::put('/profile/tool-visibility', [ProfileController::class, 'updateToolVisibility'])->name('profile.tool-visibility.update')` alongside the existing `profile.auth-preferences.update` route.
- [x] 3.3 Add `resources/views/profile/components/tool-visibility-preference.blade.php` — a `<form>` posting to `profile.tool-visibility.update` with one `.keystone-checkbox-label` for `show_tool_payloads`, styled consistently with `auth-preferences.blade.php`.
- [x] 3.4 In `resources/views/profile/show.blade.php`, add a new `<section>` wrapped in `@can('manage-ai-tools') ... @endcan` including the new partial.

## 4. Update the affected spec and tests

- [x] 4.1 Rewrite `tests/Feature/HostChatBotPagePayloadTest.php::test_chat_page_redacts_tool_arguments_and_output_in_production` (rename to drop "in_production" from the name) to cover: a user with `manage-ai-tools` + `show_tool_payloads=true` sees payloads; the same user with `show_tool_payloads=false` doesn't; a user without `manage-ai-tools` (even with `show_tool_payloads=true` forced in the DB) doesn't; a guest doesn't. Drop the `$this->app->detectEnvironment(...)` call entirely — no longer the relevant variable.
- [x] 4.2 Verify (don't just assume) `tests/Feature/ChatBotControllerTest.php`'s guest-message test still passes unchanged — its `usingToolPayloads()` mock expectation is already `zeroOrMoreTimes()`, which should tolerate a guest never triggering the call under the new gate.

## 5. Verification

- [x] 5.1 Run `vendor/bin/phpunit` — full suite passes, including the rewritten test from 4.1.
- [x] 5.2 Manually verify (confirmed by the developer 2026-08-30): as a user with `manage-ai-tools`, the profile page shows the toggle; enabling it and sending a message that calls a tool shows input/output live and after reload, in your actual `APP_ENV` (not just non-production); as a user without `manage-ai-tools`, no toggle appears and no tool payloads show even if `show_tool_payloads` is manually set to `true` in the database.
- [x] 5.3 Confirm no changes were made under `/home/jasonv/Code/@jvjvjv/code-talker` for this change.

## 6. Pre-existing blockers fixed to reach the profile page

Both predate this change (introduced in `cc8a191`, the host-owned auth migration) and made `/profile` return a 500 for every user, so the new toggle could not be rendered or tested until they were fixed. Approved as in-scope during implementation.

- [x] 6.1 Fix `@include('auth.keystone-styles')` → `components.auth.keystone-styles` in 10 profile/auth partials — the view only exists at `resources/views/components/auth/keystone-styles.blade.php`
- [x] 6.2 Fix three Blade references to non-existent Fortify route names: `two-factor.store` → `two-factor.enable`, `two-factor.destroy` → `two-factor.disable`, `two-factor.recovery-codes.regenerate` → `two-factor.regenerate-recovery-codes` (HTTP verbs already matched)

## 7. Test coverage added beyond the original task list

- [x] 7.1 Add `tests/Feature/ProfileToolVisibilityTest.php` — the `tool-payload-visibility-preference` spec's four toggle scenarios (permitted user sees it, unpermitted never does, setting without permission is rejected, preference persists) had no task covering them
- [x] 7.2 Update `test_chat_page_exposes_tool_activity_for_a_message_that_used_tools` (renamed to `..._for_a_permitted_opted_in_user`) — it made a guest request and passed only because of the old non-production check; under the new gate a guest is always redacted
