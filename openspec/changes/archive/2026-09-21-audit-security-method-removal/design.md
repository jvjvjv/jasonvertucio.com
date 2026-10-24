## Context

Two auth subsystems must remain decoupled (see proposal.md - Why):

- **2FA**: pure Fortify. `Laravel\Fortify\Http\Controllers\TwoFactorAuthenticationController::destroy()`
  calls `Laravel\Fortify\Actions\DisableTwoFactorAuthentication::__invoke($user)` with no
  app-owned controller in the path. There is no custom action override registered in
  `app/Providers/FortifyServiceProvider.php` for this action (only `createUsersUsing`,
  `updateUserProfileInformationUsing`, `updateUserPasswordsUsing`,
  `resetUserPasswordsUsing`, `redirectUserForTwoFactorAuthenticationUsing` are bound).
- **Passkeys**: fully app-owned. `App\Http\Controllers\Auth\PasskeyController::destroy()`
  loads the passkey via `$request->user()->passkeys()->findOrFail($passkeyId)` and calls
  `$passkey->delete()` directly (no `PasskeyService` method wraps deletion today).

**Confirmed via vendor source** (per task instructions, checked before designing):
`vendor/laravel/fortify/src/Actions/DisableTwoFactorAuthentication.php` unconditionally
dispatches `Laravel\Fortify\Events\TwoFactorAuthenticationDisabled::dispatch($user)`
after nulling `two_factor_secret`, `two_factor_recovery_codes`, and (conditionally)
`two_factor_confirmed_at`, provided at least one of those three columns was non-null
before the call. This event **does exist and is already dispatched** in the installed
Fortify version — no need to wrap/decorate the action class. Its base class
(`TwoFactorAuthenticationEvent`) carries a single public `$user` property, which is the
*same model instance* the action just saved, so Eloquent's post-save change tracking
(`wasChanged()`) is still available on it within that request.

Two important nuances found during this inspection:

1. **The disable action fires even for an unconfirmed setup.** `TwoFactorAuthenticationController::destroy()`
   calls the disable action unconditionally — there is no guard requiring
   `two_factor_confirmed_at` to have been set. A user who starts 2FA setup (secret
   generated) and cancels before confirming will still trigger
   `TwoFactorAuthenticationDisabled`, even though `User::hasTwoFactorEnabled()` was
   already `false` (it requires both secret and confirmation). This must not count as a
   downgrade — there was nothing active to downgrade from.
2. **By the time the event fires, `hasTwoFactorEnabled()` is always `false`** — the
   model was already mutated and saved before dispatch. So "was 2FA actually enabled
   before this disable" cannot be answered by calling `hasTwoFactorEnabled()` on
   `$event->user` post-hoc; it must be answered from the model's tracked change, i.e.
   `$event->user->wasChanged('two_factor_confirmed_at')` combined with
   `$event->user->getOriginal('two_factor_confirmed_at')` being non-null. Both are
   available because `$event->user` is the exact instance `save()`d by the action.

`User` (`app/Models/User.php`) already exposes `hasTwoFactorEnabled()` and
`hasPasskeysRegistered()`, per this repo's CLAUDE.md.

This app uses the Laravel 11+ structure: no `app/Providers/EventServiceProvider.php`
exists. Event-listener wiring must either use attribute-based listener discovery
(`#[AsEventListener]`) or be registered explicitly in `bootstrap/app.php` / a small new
provider, following whatever convention the existing providers use — a task-time
decision, not a spec-level one.

## Goals / Non-Goals

**Goals:**
- Detect a "last method of a kind removed" downgrade on both the 2FA and passkey paths
  without either subsystem referencing the other.
- Keep the audit/alert side effects fully decoupled from, and non-blocking to, the
  removal actions themselves (per the spec's async requirement).

**Non-Goals:**
- Reasoning about overall account security posture across method *kinds* (e.g. whether
  the user still has password login). Per proposal scope, "last method" is evaluated
  per kind (2FA vs. passkey) independently, matching the CLAUDE.md-referenced helper
  methods (`hasTwoFactorEnabled()`, `hasPasskeysRegistered()`), each of which only
  answers for its own kind. This is a deliberate scope choice, recorded here since the
  proposal's parenthetical ("not on every removal if they have other methods left")
  could be read either way.
- Changing Fortify's or the passkey routes' HTTP responses, status codes, or timing.
- A general-purpose security-audit UI; this change only creates the storage and the
  event plumbing (see proposal.md - Impact for the new `SecurityAuditLogEntry` model).

## Decisions

### 1. `UserSecurityMethodRemoved` domain event shape
```php
final class UserSecurityMethodRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly SecurityMethodKind $kind, // enum: TwoFactor | Passkey
        public readonly int $remainingMethodsOfKind, // always 0 when dispatched
        public readonly \DateTimeImmutable $removedAt,
    ) {}
}
```
`remainingMethodsOfKind` is carried even though it is always `0` at dispatch time (that's
the whole point of the "last one" gate) — it documents intent for listeners and keeps the
event self-describing if a future kind ever allows partial downgrades. `SecurityMethodKind`
is a new backed enum (`TitleCase` per this repo's PHP conventions:
`TwoFactor`, `Passkey`) so listeners and the audit-log column use a closed, typed set
rather than a free-text string.

**Alternative considered**: reuse Fortify's own event object directly in the listener
without an app-level event. Rejected — it would leak a Fortify type into the passkey
path's mental model and into `LogSecurityAuditEntry`, defeating the point of a shared,
package-agnostic event.

### 2. Dispatch point 1 — 2FA, via a listener on Fortify's own event
A new listener, `App\Listeners\Auth\DetectTwoFactorSecurityDowngrade`, subscribes to
`Laravel\Fortify\Events\TwoFactorAuthenticationDisabled`. Detection logic:

```php
public function handle(TwoFactorAuthenticationDisabled $event): void
{
    $user = $event->user;

    if (! $user->wasChanged('two_factor_confirmed_at')
        || is_null($user->getOriginal('two_factor_confirmed_at'))) {
        return; // was never actually confirmed/active - not a downgrade
    }

    UserSecurityMethodRemoved::dispatch(
        $user,
        SecurityMethodKind::TwoFactor,
        remainingMethodsOfKind: 0, // 2FA has no multi-method concept - always last
        removedAt: now()->toImmutable(),
    );
}
```
This is why the design does **not** need to wrap/decorate `DisableTwoFactorAuthentication`
(step 1's research confirmed the event exists and already fires): listening to Fortify's
own event is sufficient and keeps this change purely additive to Fortify's action.
2FA is inherently single-method per user, so "was previously confirmed" is equivalent to
"this was the last (only) 2FA method" — no separate remaining-count check is needed, unlike
passkeys.

**Alternative considered**: override `DisableTwoFactorAuthentication` via a custom bound
action (as Fortify supports for several other actions in
`FortifyServiceProvider::boot()`). Rejected — Fortify has no
`Fortify::disableTwoFactorAuthenticationUsing()`-style binding hook for this specific
action, and re-implementing framework-native logic just to add a side effect duplicates
maintenance surface the dispatched event already avoids.

### 3. Dispatch point 2 — passkeys, in `PasskeyController::destroy()` / `PasskeyService`
Move the deletion into a new `PasskeyService::deletePasskey(User $user, string $passkeyId): void`
method (today `destroy()` deletes inline), so the "last one" check and the deletion are
one auditable unit rather than split between controller and service ad hoc:

```php
public function deletePasskey(Authenticatable $user, string $passkeyId): void
{
    $passkey = $user->passkeys()->findOrFail($passkeyId);
    $passkey->delete();

    if (! $user->hasPasskeysRegistered()) {
        UserSecurityMethodRemoved::dispatch(
            $user,
            SecurityMethodKind::Passkey,
            remainingMethodsOfKind: 0,
            removedAt: now()->toImmutable(),
        );
    }
}
```
Checking `hasPasskeysRegistered()` **after** the delete (rather than counting before) is
correct here because, unlike the 2FA case, nothing upstream has already mutated the
"before" state out of reach — the count is a fresh query, so post-delete `false` is
unambiguous. `PasskeyController::destroy()` calls this new method instead of deleting
directly, keeping the "last one" logic in the service layer alongside the rest of
`PasskeyService`.

### 4. Listeners are queued, both implement `ShouldQueue`
- `App\Listeners\LogSecurityAuditEntry` — writes a `SecurityAuditLogEntry` row
  (`user_id`, `kind`, `removed_at`, timestamps). A minimal model/migration is added as
  part of this change (no existing audit-log store to reuse).
- `App\Listeners\AlertUserOfSecurityDowngrade` — queues a new mailable,
  `App\Mail\SecurityMethodRemovedMail`, addressed to `$event->user->email`.

Both queue onto the `default` queue connection/queue, consistent with
`CommentReceivedMail` (this repo's only other queued work, per CLAUDE.md) and the
existing `queue:work --queue=default` supervisor config — no new queue name needed.

### 5. Wiring without `EventServiceProvider`
Laravel 11+ structure (confirmed: no `app/Providers/EventServiceProvider.php` in this
repo). Use PHP 8 attribute-based discovery — `#[AsEventListener(event: TwoFactorAuthenticationDisabled::class)]`
on `DetectTwoFactorSecurityDowngrade::handle()`, and likewise
`#[AsEventListener(event: UserSecurityMethodRemoved::class)]` on the two downstream
listeners — rather than introducing a new provider file, since Laravel auto-discovers
attributed listeners without extra registration. This keeps wiring next to the listener
class itself instead of a separate mapping file, matching the framework-default
convention for this Laravel version.

## Risks / Trade-offs

- **[Risk]** `wasChanged()`/`getOriginal()` on `$event->user` depend on that exact model
  instance being the one Fortify's action saved, within the same request — a future
  Fortify upgrade could change `DisableTwoFactorAuthentication` to dispatch with a
  freshly-refetched model, silently breaking the "was it previously confirmed" check. →
  **Mitigation**: cover with a feature test that asserts no downgrade event fires when
  disabling never-confirmed 2FA, so a Fortify upgrade regression is caught by CI rather
  than silently misfiring alerts.
- **[Risk]** Two independent listeners for one event means a partial failure (e.g. mail
  queue down) can log an audit entry with no alert sent, or vice versa. → **Mitigation**:
  each listener queues independently (per spec's async/non-blocking requirement) and
  failures are Laravel's standard queued-job failure handling (`failed_jobs` table);
  no cross-listener transaction is introduced, since coupling their success would
  reintroduce the coupling this design avoids elsewhere.
- **[Risk]** Forgetting to restart `queue:work` after deploy (per this repo's existing
  documented gotcha in CLAUDE.md) means new listeners silently don't run. →
  **Mitigation**: no new mitigation beyond the existing documented deploy step; call
  this out explicitly in tasks.md so it isn't missed for this specific change.

## Migration Plan

1. Add `security_audit_log` migration and `SecurityAuditLogEntry` model.
2. Add `SecurityMethodKind` enum and `UserSecurityMethodRemoved` event.
3. Add `DetectTwoFactorSecurityDowngrade` listener (Fortify event → app event).
4. Add `PasskeyService::deletePasskey()` and switch `PasskeyController::destroy()` to
   call it.
5. Add `LogSecurityAuditEntry` and `AlertUserOfSecurityDowngrade` listeners plus
   `SecurityMethodRemovedMail`.
6. Deploy, then restart the `queue:work` supervisor program per CLAUDE.md's documented
   deploy step (new queued listeners won't run on the process Laravel booted before this
   deploy).

No rollback complexity beyond a normal revert: no existing behavior is changed, only new
tables/listeners are added, so reverting the commit and the migration is sufficient.
