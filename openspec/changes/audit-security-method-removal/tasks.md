## 1. Data model

- [x] 1.1 Create migration for `security_audit_log` (`user_id` FK to `users`, `kind`
      string/enum column, `removed_at` timestamp, standard timestamps) via
      `php artisan make:migration create_security_audit_log_table --no-interaction`
      and verify `php artisan migrate` runs cleanly against both the `jasonvertucio`
      dev DB and the `wink` test DB (per this repo's CLAUDE.md dual-DB convention).
- [x] 1.2 Create `App\Models\SecurityAuditLogEntry` (Eloquent model, `casts()` method
      for `kind` and `removed_at`, `belongsTo(User::class)`) and verify a factory
      (`php artisan make:factory SecurityAuditLogEntryFactory --no-interaction`) can
      create a row in `php artisan tinker`.
- [x] 1.3 Create `App\Enums\SecurityMethodKind` backed enum with cases `TwoFactor` and
      `Passkey` and verify it is usable as the migration/model's `kind` cast.

## 2. Domain event

- [x] 2.1 Create `App\Events\UserSecurityMethodRemoved` per design.md's shape (`user`,
      `kind`, `remainingMethodsOfKind`, `removedAt`) using
      `php artisan make:event UserSecurityMethodRemoved --no-interaction` and verify it
      is dispatchable in isolation via a quick unit test asserting constructor
      properties are set.

## 3. 2FA dispatch point (Fortify integration)

- [x] 3.1 Create `App\Listeners\Auth\DetectTwoFactorSecurityDowngrade` implementing
      `ShouldQueue`, listening to `Laravel\Fortify\Events\TwoFactorAuthenticationDisabled`
      via `#[AsEventListener]`, applying the `wasChanged('two_factor_confirmed_at')` +
      `getOriginal('two_factor_confirmed_at')` non-null guard from design.md, and
      dispatching `UserSecurityMethodRemoved` with `kind: TwoFactor`,
      `remainingMethodsOfKind: 0`.
- [x] 3.2 Write a feature test that confirms 2FA, then calls Fortify's
      `DELETE /user/two-factor-authentication`, and asserts
      `UserSecurityMethodRemoved` is dispatched with `kind: TwoFactor` (use
      `Event::fake([UserSecurityMethodRemoved::class])` and `Event::assertDispatched`).
- [x] 3.3 Write a feature test covering design.md's risk case: generate a 2FA secret
      but never confirm it, call the disable route, and assert
      `UserSecurityMethodRemoved` is NOT dispatched — this is the regression guard
      called out in design.md's Risks section for a future Fortify upgrade changing
      how the event's `$user` is constructed.

## 4. Passkey dispatch point

- [x] 4.1 Add `PasskeyService::deletePasskey(Authenticatable $user, string $passkeyId): void`
      per design.md (find-or-fail, delete, then dispatch `UserSecurityMethodRemoved`
      with `kind: Passkey` only when `! $user->hasPasskeysRegistered()` post-delete).
- [x] 4.2 Update `App\Http\Controllers\Auth\PasskeyController::destroy()` to call
      `PasskeyService::deletePasskey()` instead of deleting the passkey inline, and
      verify existing passkey-deletion feature tests (if any) still pass unchanged —
      the controller's response contract must not change per proposal.md's Impact.
- [x] 4.3 Write a feature test: user with two passkeys removes one, assert
      `UserSecurityMethodRemoved` is NOT dispatched (`Event::assertNotDispatched`).
- [x] 4.4 Write a feature test: user with one passkey removes it, assert
      `UserSecurityMethodRemoved` IS dispatched with `kind: Passkey`.

## 5. Listeners: audit log and alert email

- [x] 5.1 Create `App\Listeners\LogSecurityAuditEntry` implementing `ShouldQueue`,
      listening to `UserSecurityMethodRemoved` via `#[AsEventListener]`, writing a
      `SecurityAuditLogEntry` row, and verify with a feature test using
      `Queue::fake()` + running the listener synchronously (or `Bus::assertDispatched`
      pattern per this repo's existing queued-mail test convention noted in
      CLAUDE.md's Queues section) that a row is created with the correct `user_id`
      and `kind`.
- [x] 5.2 Create `App\Mail\SecurityMethodRemovedMail` (mailable, addressed to
      `$event->user->email`, states which method kind was removed) and a Blade view
      for it under `resources/views/mail/`.
- [x] 5.3 Create `App\Listeners\AlertUserOfSecurityDowngrade` implementing
      `ShouldQueue`, listening to `UserSecurityMethodRemoved` via `#[AsEventListener]`,
      and queuing `SecurityMethodRemovedMail`; verify with a feature test using
      `Mail::fake()` + `Mail::assertQueued(SecurityMethodRemovedMail::class, ...)` per
      CLAUDE.md's noted convention (`QUEUE_CONNECTION=sync` in `phpunit.xml`, so use
      `Mail::fake()` rather than relying on the queue driver).
- [x] 5.4 Write an end-to-end feature test: last passkey removed via the real HTTP
      route → both a `SecurityAuditLogEntry` row exists AND
      `SecurityMethodRemovedMail` is queued to the correct user, confirming the two
      listeners react independently to the same event without referencing each other
      or the passkey/Fortify subsystems directly.

## 6. Non-blocking guarantee

- [x] 6.1 Write a feature test confirming the passkey-removal HTTP response (status
      code / redirect / JSON body) is unchanged when `PasskeyService::deletePasskey()`
      triggers a downgrade, satisfying the spec's "does not alter the removal action's
      outcome" requirement — assert the response is identical to the no-downgrade case
      from task 4.3's fixture, aside from the dispatched event.

## 7. Deployment note

- [x] 7.1 Add a line to the deploy runbook / PR description reminding that
      `queue:work` must be restarted after this deploy (`sudo supervisorctl restart
      <program-name>`), per CLAUDE.md's Queues section — the two new listeners are
      queued work and won't run on an already-booted worker process. Verify by
      confirming the reminder is present in the PR description at merge time (no code
      artifact for this task).
