## 1. Prerequisites and decisions

- [ ] 1.1 Hand `bspdx-com-instructions.md` to the developer for the bspdx.com agent; verify the developer has relayed back: client id (secret via a secure channel, never committed), registered callback URLs, the scope name, and confirmation that MFA enforcement is live — record the non-secret answers in design.md Open Questions
- [x] 1.2 Get approval for the OAuth 2.0 client dependency (custom Socialite provider vs `league/oauth2-client` `GenericProvider`) before installing; verify `composer.json` changes only after approval
- [x] 1.3 Get approval to uninstall `spatie/laravel-passkeys` (Phase B); if not approved, leave the package installed with no reachable routes and record that in design.md

## 2. Phase A — data and linking (additive, deploy first)

- [x] 2.1 Create the `federated_identities` migration (`foreignUuid('user_id')` → `users.id` cascade on delete, `provider`, `subject`, `email_at_link`, timestamps, unique `provider`+`subject`, unique `user_id`+`provider`) and `FederatedIdentity` model + factory; run it with `--path` against both `jasonvertucio` and `wink` and verify the table in both
- [x] 2.2 Add `User::federatedIdentities()`; verify with a model test that deleting a user removes its identities
- [x] 2.3 Add `php artisan user:link-bspdx {email} {subject}` that links an existing user and refuses when the user or subject is already linked or the user does not exist; verify each case with a feature test

## 3. Phase A — federated flow

- [x] 3.1 Add `services.bspdx` config (`enabled`, `base_url`, `client_id`, `client_secret`, `redirect`, `scopes`, timeout) backed by `env()`, with authorize/token/userinfo paths derived from `base_url`, and a "configured" check that also requires `base_url` to be `https://`; verify `php artisan config:show services.bspdx` shows `enabled=false` by default
- [x] 3.2 Implement redirect + callback: PKCE (S256) and `state` in the session, code exchange at `/oauth/token`, then a server-to-server `GET /api/oauth/userinfo` with the returned access token (not stored), both calls with `withoutRedirecting()` and short timeouts; verify each failure (state mismatch, denied consent, rejected code/verifier, userinfo error, userinfo missing `id` or `email`, redirect response, timeout) ends at `/login` with a generic error and no session
- [x] 3.3 Implement account resolution per design.md (subject match with email sync when no collision → refuse when the email belongs to any local user → create user with no roles, random unusable password, display name rule, no `Registered` event); verify every scenario in `specs/federated-login/spec.md`
- [x] 3.4 Establish the session with `Auth::login` (no remember-me) and session regeneration, redirect to the intended URL; verify a new user cannot reach admin pages
- [x] 3.5 Add a `federated-login` rate limiter keyed on the Cloudflare-aware client address and apply it to `GET /auth/bspdx/redirect` and `GET /auth/bspdx/callback`; verify throttling
- [x] 3.6 Show a "Sign in with bspdx.com" control on the existing login page only when `services.bspdx.enabled` and the config check passes; verify both states

## 4. Phase B — login page redesign (cutover)

- [ ] 4.1 Rewrite `resources/views/auth/login.blade.php` as a single "Sign in with bspdx.com" control plus the generic error area: no email or password fields, no method-discovery JS, no TOTP or passkey scripts; keep the named route `login` (`GET /login`) so guest redirects and existing tests still resolve; verify guest redirect from an `auth` route lands on it and it renders with and without a flashed error
- [ ] 4.2 Remove `POST /login/check-email` and `LoginMethodsController`; verify the route no longer exists

## 5. Phase B — profile page redesign

- [ ] 5.1 Remove the auth-preferences, two-factor setup and passkey-management components from `resources/views/profile/*` and the `updateAuthPreferences` action/route; add a read-only "bspdx.com account" section (linked email, linked date, link to manage the account at bspdx.com); move the remaining profile routes from `routes/passkeys.php` to `routes/web.php`; verify the profile page renders for a linked user and the tool-visibility preference still saves (`ProfileToolVisibilityTest`)

## 6. Phase B — remove local credentials

- [ ] 6.1 Empty `config/fortify.php` `features`, call `Fortify::ignoreRoutes()`, and register `POST /logout` explicitly to Fortify's `AuthenticatedSessionController@destroy` under `web` + `auth`; verify `session-logout` tests (`tests/Feature/Auth/LogoutTest.php`) still pass and `POST /login`, `/register`, `/forgot-password`, `/reset-password`, `/user/password`, `/user/confirm-password`, `/two-factor-challenge`, `/user/two-factor-*` return 404/405
- [ ] 6.2 Remove `routes/passkeys.php`, `PasskeyController`, `PasskeyService`, `PasswordlessLoginController`, `ConfirmPasswordController`, `ThrottlesAuthentication` (if now unused), the 2FA challenge view and the vendor passkey views; remove the Fortify 2FA challenge wiring from `FortifyServiceProvider`; verify passkey and TOTP sign-in endpoints no longer exist
- [ ] 6.3 Remove `User` auth-preference helpers (`hasTwoFactorEnabled`, `hasPasskeysRegistered`, `canUsePasswordlessLogin`, `getAvailableAuthMethods`, `hasValidAuthConfiguration`) and update `user:list`/`user:info` output that used them; remove `TwoFactorAuthenticatable`/`InteractsWithPasskeys` only if 1.3 is approved; verify `php artisan user:list` and `user:info` run
- [ ] 6.4 Remove `ChangeUserPasswordCommand` and `CreateUserCommand`; check `MakeUserCommand` and remove it if it only creates password users; verify `php artisan list user` shows `user:link-bspdx` and no password commands
- [ ] 6.5 Remove the security-downgrade audit: `UserSecurityMethodRemoved`, `LogSecurityAuditEntry`, `AlertUserOfSecurityDowngrade`, `DetectTwoFactorSecurityDowngrade`, `SecurityMethodRemovedMail`, `SecurityMethodKind` and their `AppServiceProvider` registrations; keep the `security_audit_log_entries` table and model; verify `php artisan event:list` shows none of them
- [ ] 6.6 Do not drop any column or table (`password`, `two_factor_*`, `allow_*`, `require_password`, passkeys); verify no Phase B migration touches them

## 7. Tests

- [ ] 7.1 Build a fake provider with `Http::fake` (token and userinfo endpoints) and cover the flow failures from 3.2 and the success path
- [ ] 7.2 Feature tests for account resolution: new identity, returning identity with email change (no collision and collision), unlinked identity with an existing local email refused, missing email refused, new user has no roles
- [ ] 7.3 Feature tests that password, passkey and authenticator-code sign-in no longer authenticate, and that the login and profile pages match the spec
- [ ] 7.4 List the test files that cover removed features (`AlertUserOfSecurityDowngradeTest`, `DetectTwoFactorSecurityDowngradeTest`, `LogSecurityAuditEntryTest`, `PasskeyRemovalNonBlockingTest`, `PasskeyServiceDowngradeTest`, `SecurityMethodRemovalEndToEndTest`, `Unit/Events/UserSecurityMethodRemovedTest`, and any others found) and get the developer's approval before deleting them; verify only approved files are removed
- [ ] 7.5 Run `vendor/bin/phpunit --filter 'Auth|Login|Federated|Profile|Logout|User'` and confirm passing

## 8. Documentation and rollout

- [ ] 8.1 Rewrite `.claude/context/auth.md` for bspdx.com-only sign-in (flow, `federated_identities`, `user:link-bspdx`, removed methods, `CANVAS_GUARD` must stay `web`, accepted risks); update `CLAUDE.md` (architecture list, auth bullets, user commands, Security Features logout note)
- [ ] 8.2 Write the developer-owned rollout checklist from design.md Migration Plan into this change (bspdx.com prerequisites done, Phase A deployed and tested, existing users linked with `user:link-bspdx` in each environment, owner sign-in confirmed through bspdx.com, then Phase B); do not deploy either phase as part of this change
