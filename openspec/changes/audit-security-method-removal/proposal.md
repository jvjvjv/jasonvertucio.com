## Why

Two-factor authentication is managed entirely by Fortify's own native routes (no custom
controller) and passkeys are managed by `App\Http\Controllers\Auth\PasskeyController` /
`App\Services\Auth\PasskeyService` (Spatie Laravel Passkeys). Neither package is aware of
the other, so there is currently no shared trail when a user is left with a weaker
account security posture — e.g. their only 2FA method is disabled, or their only passkey
is removed. Nobody is notified and nothing is recorded. Since these are two independent
subsystems that must not be coupled directly to each other, an event is the natural
connective tissue: each subsystem announces "the user's last method of this kind is
gone," and shared listeners handle the audit trail and the user-facing alert without
either subsystem knowing the other exists.

## What Changes

- Add a new domain event `App\Events\UserSecurityMethodRemoved`, dispatched only when a
  user is left with **zero** remaining methods of a given kind after a removal (i.e. the
  last TOTP 2FA disable, or the last passkey delete) — not on every disable/delete when
  other methods of that kind (or other passkeys) remain.
- Detect the Fortify TOTP-disable path by listening for Fortify's own
  `Laravel\Fortify\Events\TwoFactorAuthenticationDisabled` event (confirmed to exist and
  to already be dispatched by `Laravel\Fortify\Actions\DisableTwoFactorAuthentication`)
  rather than adding a custom controller — no existing hook point to edit.
- Detect the passkey-removal path in `PasskeyController::destroy()` /
  `PasskeyService`, after the passkey record is deleted.
- Add two queued listeners on `UserSecurityMethodRemoved`:
  - `LogSecurityAuditEntry` — writes an audit trail row (new `security_audit_log` table
    via a new `SecurityAuditLogEntry` model).
  - `AlertUserOfSecurityDowngrade` — queues a mail to the user warning that their account
    just lost a security method.
- No changes to `PasskeyController`'s or Fortify's public HTTP contract; this is
  additive, internal wiring only.

## Capabilities

### New Capabilities

- `security-method-removal-audit`: detecting "last method of a kind removed" for 2FA and
  passkeys, dispatching a shared domain event, and reacting to it with an audit log entry
  and a user-facing downgrade alert email.

### Modified Capabilities

_None._ No existing spec's requirements change; passkey deletion's externally observable
behavior (response, status codes) is unchanged — only new internal side effects are added.

## Impact

- **New**: `app/Events/UserSecurityMethodRemoved.php`,
  `app/Listeners/LogSecurityAuditEntry.php`,
  `app/Listeners/AlertUserOfSecurityDowngrade.php`,
  `app/Models/SecurityAuditLogEntry.php`, a migration for `security_audit_log`,
  a mailable for the downgrade alert, and a listener/subscriber that reacts to Fortify's
  `TwoFactorAuthenticationDisabled` event.
- **Modified**: `app/Http/Controllers/Auth/PasskeyController.php` (`destroy()`) and/or
  `app/Services/Auth/PasskeyService.php` to detect "last passkey removed" and dispatch
  the event; `app/Providers/*ServiceProvider.php` (or a new `EventServiceProvider`-style
  registration) to wire the Fortify listener since none currently exists in this app
  (Laravel 11+ structure — no `EventServiceProvider` by default).
- **Queue**: two new queued listeners join `CommentReceivedMail` as the application's
  queued work on the `default` database queue; production's `queue:work` must be
  restarted after this deploy per this repo's existing queue-worker convention.
- **No changes** to `config/fortify.php`, `PasskeyService`'s public method signatures, or
  any HTTP route/response contracts.
