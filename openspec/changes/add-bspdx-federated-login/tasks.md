## 1. Prerequisites and decisions

- [ ] 1.1 Confirm with the developer the provider facts bspdx.com will expose (issuer, client id/secret, claims `sub`/`email`/`email_verified`/`name`) and record them in `config/services.php` key names; verify the answers are written into design.md Open Questions
- [ ] 1.2 Get approval for the OIDC client dependency (`laravel/socialite` OIDC driver vs `league/oauth2-client`) before installing; verify `composer.json` changes only after approval

## 2. Data and models

- [ ] 2.1 Create the `federated_identities` migration (UUID `user_id` FK to `users`, `provider`, `subject`, `email_at_link`, timestamps, unique on `provider`+`subject`) and `FederatedIdentity` model + factory; run it against both `jasonvertucio` and `wink` (`DB_DATABASE=wink php artisan migrate --path=...`) and verify the table in both
- [ ] 2.2 Add `User::federatedIdentities()` relationship; verify with a model test

## 3. Federated flow

- [ ] 3.1 Add `services.bspdx` config (`enabled`, `issuer`, `client_id`, `client_secret`, `redirect`, `scopes`) backed by `env()`; verify `php artisan config:show services.bspdx` shows `enabled=false` by default
- [ ] 3.2 Implement redirect + callback controller with PKCE, state and nonce, cached discovery, and ID token validation (signature via JWKS, `iss`, `aud`, `exp`, `nonce`); verify each failure scenario ends at the login page with no session
- [ ] 3.3 Implement account resolution (subject match → verified-email link → create user with no roles and unusable random password), creating the link row; verify all spec scenarios including unverified-email refusal and missing email
- [ ] 3.4 Establish the session with `Auth::login` + session regeneration and redirect to the intended URL; verify a new user cannot reach admin pages
- [ ] 3.5 Add routes (`GET /auth/bspdx/redirect`, `GET /auth/bspdx/callback`) throttled like the existing login limiter; verify throttling

## 4. Replacing password login

- [ ] 4.1 Behind `services.bspdx.enabled`, remove the password form from `resources/views/auth/login.blade.php`, add the "Sign in with bspdx.com" button, and hide the button when provider config is incomplete; verify both states render
- [ ] 4.2 Disable Fortify password authentication and password registration when the flag is on (`config/fortify.php` features / `FortifyServiceProvider`), and stop `User::getAvailableAuthMethods()` reporting `password` in that state; verify a password POST creates no session and passkey/TOTP login still works
- [ ] 4.3 With the flag off, verify the login page and password login behave exactly as before (regression test)

## 5. Tests

- [ ] 5.1 Build a fake OIDC provider for tests (stub discovery, JWKS and token endpoint with a locally generated key) and cover: success, state/nonce mismatch, forged/expired token, denied consent, provider down
- [ ] 5.2 Feature tests for the account resolution matrix: new identity, returning identity after email change, verified-email link to existing user, unverified-email refusal, no-email refusal, new user has no roles
- [ ] 5.3 Run `vendor/bin/phpunit --filter 'Auth|Login|Federated|Passkey'` and confirm passing

## 6. Documentation and rollout

- [ ] 6.1 Update `.claude/context/auth.md` and the `CLAUDE.md` auth bullets: new flow, `federated_identities`, the feature flag, and that password login is replaced when enabled
- [ ] 6.2 Write the developer-owned rollout checklist from design.md Migration Plan (pre-flight of password-only users, owner has a passkey/TOTP, enable in non-production first) into the change notes; do not enable the flag in production as part of this change
