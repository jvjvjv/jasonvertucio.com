## Context

Current auth (see `.claude/context/auth.md`): Fortify drives password login, password reset, registration and 2FA management; Spatie Passkeys and a custom `PasswordlessLoginController` provide passkey and TOTP-only sign-in; `User::getAvailableAuthMethods()` reports `password`, `passkey`, `totp` per user and `LoginMethodsController` drives the login page. Users have a `char(36)` UUID `id`, a unique `email`, a `password` column, and Keystone roles via `HasKeystone`. Sessions are standard web sessions; logout is `POST /logout` (spec `session-logout`). `bspdx/keystone` is RBAC only and provides no authentication.

Provider facts observed: `https://bspdx.com/.well-known/openid-configuration` returns 404, and the homepage content ("The Bootstrap Paradox…") describes no identity service. The user chose OIDC / OAuth 2.0, auto-create of users, and replacing password login. Unknown until the provider exists: issuer URL, endpoints, scopes, claims, whether `email_verified` is asserted.

## Goals / Non-Goals

**Goals:**
- Authorization-code + PKCE login against bspdx.com producing a normal session.
- Auto-create users on first login, link by verified email when a local user already exists.
- Remove password sign-in and password registration.

**Non-Goals:**
- Building the OIDC provider on bspdx.com (prerequisite, separate project).
- Mapping IdP groups/claims to Keystone roles (explicitly not chosen; new users have no roles).
- Single logout / back-channel logout, refresh-token use, or calling bspdx.com APIs after login.
- Changing passkey or TOTP behavior, or `session-logout`.

## Decisions

**Protocol and client.** OIDC authorization-code with PKCE and `state`/`nonce`, confidential client. Use discovery from the configured issuer (cached) so endpoints are not hard-coded. Validate the ID token (signature via JWKS, `iss`, `aud`, `exp`, `nonce`) rather than trusting the userinfo response alone. Library: `laravel/socialite` with a generic OIDC driver is the leading option but is a new dependency — **needs approval before install**; alternative is a small purpose-built client on `league/oauth2-client`. Decision deferred to apply with your approval.

**Identity table.** New `federated_identities` (`id`, `user_id` UUID FK, `provider` string, `subject` string, `email_at_link` string, timestamps) with a unique key on (`provider`, `subject`). Login matches on subject first. Alternative: add `bspdx_sub` to `users` — rejected; a table allows more providers later and keeps `users` untouched.

**Account resolution order on callback.** (1) identity row exists → log in that user. (2) else, if the ID token's `email_verified` is true and a local user has that email → link and log in. (3) else create a user (name from claims, email, random unusable password, no roles), link, log in. If `email_verified` is not true, an existing-email match is refused rather than linked, so an unverified claim can never take over an account. The email-based link is the account-takeover-sensitive step and is tested explicitly.

**New users have no roles.** Auto-created users get no Keystone roles; an admin grants them. Login alone never confers `manage-*` or admin access.

**What "replace password login" removes.** Remove the password form and Fortify's password authentication, and disable `Features::registration()` and password reset for new flows. Keep passkey and passwordless-TOTP sign-in for users who already enabled them (assumption — the request names password login only). This is the break-glass path if bspdx.com is unavailable. `getAvailableAuthMethods()` stops reporting `password`; `require_password` becomes unused and is left in place for a later cleanup migration.

**Safe rollout.** Gate the change behind a config flag (`services.bspdx.enabled`) so the federated button and the removal of password login switch together and can be reverted by config. Do not remove password code paths in the same commit that enables the flag by default; verify a federated login against a real or stub provider first.

**Session.** After validation, `Auth::login($user)` with session regeneration, then redirect to the intended URL. 2FA challenges do not apply to federated sessions (the IdP is responsible for the second factor).

**Failure handling.** Denied consent, state/nonce mismatch, invalid token, unreachable provider, or a missing email claim end at the login page with a generic error and no session; details go to the log only.

## Risks / Trade-offs

- **[Risk] Provider does not exist yet.** Nothing here can be exercised end to end until bspdx.com serves OIDC. → Mitigation: develop against a fake provider in tests (stubbed discovery/JWKS/token endpoints); list provider requirements in the spec; keep the feature flag off until it exists.
- **[Risk] Lockout.** Replacing passwords makes bspdx.com availability and account ownership a single point of failure for the admin area. → Mitigation: keep passkey/TOTP sign-in; before enabling, confirm the owner account has a working passkey or TOTP; flag defaults to off.
- **[Risk] Email-based linking can be abused** if the IdP lets users assert unverified emails. → Mitigation: link only on `email_verified: true` and a unique email; otherwise refuse.
- **[Risk] Open sign-up.** Auto-create means anyone with a bspdx.com account gets a local user (comment-capable, no roles). → Accepted by choice; mitigated by no roles, existing comment moderation, and the provider's own account policy.
- **[Trade-off] Removing password reset/registration** deletes working code paths; existing password-only users must have a bspdx.com account with the same verified email or they lose access. → Pre-flight list of users with `require_password` and no passkey/TOTP before enabling.

## Migration Plan

1. Land code and migration with `services.bspdx.enabled=false`; password login unchanged.
2. Stand up the bspdx.com OIDC provider and register this site as a client (out of repo).
3. Pre-flight: list password-only users and confirm each has a matching verified email at bspdx.com; confirm the owner has a passkey or TOTP.
4. Enable the flag in a non-production environment, test the full flow, then enable in production (developer-owned deploy).
5. Rollback: set the flag off to restore password login; the identity table can remain.

## Open Questions

- Issuer URL, client credentials, and which claims bspdx.com will release (`email`, `email_verified`, `name`, a stable `sub`).
- Confirm passkeys and passwordless TOTP should remain (assumed yes).
- Dependency choice: Socialite OIDC driver vs. `league/oauth2-client`.
- Should password-only users with no bspdx.com match be given a grace period or a one-time recovery path?
