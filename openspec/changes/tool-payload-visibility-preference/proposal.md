## Why

Tool call arguments and results (`ToolPanel.input`/`output`) are currently shown or hidden by a single blanket check: `app()->environment('production')`. That's an all-or-nothing environment switch, not a real access control — every visitor sees the same thing, and it can never be turned on in production no matter who's asking. The user wants tool payloads visible in production too, but only for trusted users who explicitly opt in: gated on the `manage-ai-tools` permission (the same permission that already gates the entire AI admin surface — there's no more specific "create chat bot" permission in this app), and only when that user has separately turned the preference on. Anyone without the permission — including every guest — sees no tool payloads at all, in any environment, no toggle to find.

## What Changes

- Add a `show_tool_payloads` boolean preference on `User` (default off), settable only by users who hold `manage-ai-tools` — the toggle itself doesn't appear in the profile page for anyone else.
- Replace the `app()->environment('production')` check in `ChatBotController::message()` and `HostChatBotPagePayload::toolPanelsFor()`/`transcriptWithToolPanels()` with `$user?->can('manage-ai-tools') && $user->show_tool_payloads` — evaluated fresh per request from the current user, not cached, so revoking the permission hides payloads immediately even if the stored preference is still on.
- Add a profile settings section (mirroring the existing `updateAuthPreferences()` pattern) for flipping the preference, visible only `@can('manage-ai-tools')`.
- Tool payloads become visible in production for permitted, opted-in users; every other visitor (including all guests, and any authenticated user without the permission) never sees them, in any environment.

## Capabilities

### New Capabilities
- `tool-payload-visibility-preference`: A per-user, permission-gated preference controlling whether tool call arguments/results are shown in chat, replacing the environment-based gate.

### Modified Capabilities
- `chat-tool-call-persistence`: "Historical tool payloads are redacted in production exactly as the live stream is" no longer describes the actual gate (permission + preference, not environment) — this requirement needs updating to match.

## Impact

- **Affected code**: `app/Http/Controllers/ChatBotController.php`, `app/Services/ChatBot/HostChatBotPagePayload.php`, `app/Models/User.php`, a new migration on `users`, `app/Http/Controllers/Auth/ProfileController.php`, `resources/views/profile/show.blade.php` + a new component partial.
- **Tests needing updates**: `tests/Feature/HostChatBotPagePayloadTest.php`'s `test_chat_page_redacts_tool_arguments_and_output_in_production` currently forces the environment and asserts redaction against a guest request — needs rewriting to actually exercise permission/preference combinations, since a guest is redacted either way and the test's current premise (environment-driven) will be false. `tests/Feature/ChatBotControllerTest.php`'s guest-message test mocks `usingToolPayloads()` as `zeroOrMoreTimes()`, which tolerates the new gate's behavior unchanged (guest → never called) — verify, don't assume.
- **Not affected**: `jvjvjv/code-talker` package — both call sites are host-owned, same as the change that added the environment gate in the first place.
