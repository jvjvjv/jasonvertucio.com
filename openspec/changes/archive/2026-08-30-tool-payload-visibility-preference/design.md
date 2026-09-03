## Context

Two call sites currently gate tool payload visibility purely on environment:

- `ChatBotController::message()`:
  ```php
  if (! app()->environment('production')) {
      $this->conversationService->usingToolPayloads();
  }
  ```
- `HostChatBotPagePayload::transcriptWithToolPanels()`:
  ```php
  $includePayloads = ! app()->environment('production');
  ```

Both need to become a permission-and-preference check instead. `App\Models\User` already has a per-user boolean preference pattern (`allow_totp_login`, `allow_passkey_login`, `require_password` — plain `$fillable`/`$casts` booleans, no dedicated accessor), a matching `ProfileController::updateAuthPreferences()` update method, a `PUT /profile/auth-preferences` route, and a Blade partial (`resources/views/profile/components/auth-preferences.blade.php`) rendering one `<label class="keystone-checkbox-label">` per boolean inside a `<form>` posting to that route. `manage-ai-tools` is the only relevant permission (Keystone, checked via `$user->can('manage-ai-tools')`); there is no more specific "create chat bot" permission in this app, confirmed by searching Keystone role/permission definitions.

Both call sites already have the current user available: `ChatBotController::message()` via `$request->user()`, and `HostChatBotPagePayload` via its existing constructor-injected `Request $request` (already used for `previousHref()`).

## Goals / Non-Goals

**Goals:**
- Tool payloads are visible in production for a user who both holds `manage-ai-tools` and has the new preference on.
- A user without `manage-ai-tools` never sees the preference toggle at all, and never sees tool payloads regardless of what's stored in the database for them (defense in depth: permission is re-checked live every request, not trusted from a stale preference value).
- Every guest (no `User` row) never sees tool payloads, in any environment — `$request->user()` is `null` for guests, which naturally short-circuits the new gate to `false` with no special-casing needed.

**Non-Goals:**
- No change to `jvjvjv/code-talker` — both call sites are host-owned.
- No new permission — reuse `manage-ai-tools` rather than inventing a bot-specific one, per explicit decision.
- No UI for an admin to set this preference on behalf of another user — self-service only, same as the existing auth-preferences pattern.

## Decisions

### New `show_tool_payloads` boolean column on `users`, default `false`
Migration: `$table->boolean('show_tool_payloads')->default(false)->after('require_password')->comment('Whether this user sees tool call arguments/results in chat (requires manage-ai-tools permission too)');` — matches the existing boolean-preference column style on this table (confirmed via live schema inspection: `not null default`, short `comment()`).

**Alternative considered**: store this as a value in `config()`/env instead of per-user. Rejected — the proposal explicitly asks for a per-user toggle, not a global one.

### Gate = `$user?->can('manage-ai-tools') && $user->show_tool_payloads`, evaluated fresh per request
Both call sites compute this the same way, from the current request's user, every time — never cached on the user model or session. This means revoking `manage-ai-tools` from a user takes effect on their very next request even if `show_tool_payloads` is still `true` in the database (the column is a preference, not a grant — the permission is the actual grant). Guests (`$request->user()` is `null`) short-circuit via `?->` to `null`, which is falsy, satisfying "no permission → no payloads" without a separate guest branch.

**Alternative considered**: check the permission once and cache the boolean on the `AiConversation`/session. Rejected — a permission change should apply immediately, and re-checking a container-resolved `Gate`/`can()` call per request is cheap; no reason to introduce staleness.

### Toggle only rendered `@can('manage-ai-tools')`, mirroring the existing settings-page structure
New Blade partial `resources/views/profile/components/tool-visibility-preference.blade.php`, styled consistently with `auth-preferences.blade.php` (same `.keystone-checkbox-label` markup), included as its own `<section>` in `resources/views/profile/show.blade.php` wrapped in `@can('manage-ai-tools') ... @endcan` — a user without the permission never sees the section exists, not even as a disabled checkbox.

New route `PUT /profile/tool-visibility` → `ProfileController::updateToolVisibility()`, named `profile.tool-visibility.update`, registered alongside the existing `profile.auth-preferences.update` route. New controller method mirrors `updateAuthPreferences()`'s shape (`$request->validate(['show_tool_payloads' => 'boolean'])`, `$user->update(['show_tool_payloads' => $request->boolean('show_tool_payloads')])`, `back()->with('status', ...)`), but re-checks `$request->user()->can('manage-ai-tools')` server-side before persisting anything (authorize via `Gate::authorize()` or a `403` abort) — the route sits behind `auth` middleware like the rest of `/profile`, but is not itself permission-gated at the route level (the existing `/profile` routes aren't split by permission), so the controller method must enforce it directly rather than relying on route middleware.

**Alternative considered**: fold this into `updateAuthPreferences()` as one more field. Rejected — that method already has non-trivial "must have one auth method" business logic; mixing an unrelated tool-visibility preference into the same validated payload and error-handling path makes both harder to read, and the two settings have nothing to do with each other conceptually.

### Update the now-inaccurate spec requirement from the previous change
`openspec/specs/chat-tool-call-persistence/spec.md`'s "Historical tool payloads are redacted in production exactly as the live stream is" requirement (and its 2 scenarios) describes environment-based redaction, which this change replaces. The delta spec for this change MODIFIES that requirement to describe the permission-and-preference gate instead, keeping both call sites' behavior in sync with one documented rule rather than two now-divergent descriptions.

## Risks / Trade-offs

- **[Risk]** Forgetting to update one of the two call sites, leaving them out of sync (e.g. the live stream shows a payload the historical transcript then redacts, or vice versa) → **Mitigation**: task list updates both in the same pass, and a feature test asserts both surfaces agree for the same user/permission/preference combination.
- **[Risk]** The rewritten `test_chat_page_redacts_tool_arguments_and_output_in_production` test in `HostChatBotPagePayloadTest` previously passed by testing a guest request while forcing production — under the new gate a guest is redacted regardless of environment, so the old test's assertion would still pass without actually exercising the new logic → **Mitigation**: rewrite it to cover the real matrix — permission+preference on (visible), permission on but preference off (redacted), no permission at all even with preference somehow true (redacted) — dropping the environment-forcing entirely since it's no longer the relevant variable.
- **[Trade-off]** A user can flip `show_tool_payloads` on and then have `manage-ai-tools` revoked without the checkbox ever being explicitly unchecked — the stored preference goes stale but is inert (harmless, since the permission check still blocks visibility) until/unless the permission is restored, at which point the old preference silently reactivates. Considered acceptable: this matches how the existing auth-preference booleans behave when their prerequisites change (e.g. `allow_passkey_login` staying `true` in the DB after passkeys are deregistered — `updateAuthPreferences()` blocks *setting* it without a passkey, but doesn't retroactively clear it if a passkey is later removed elsewhere).
