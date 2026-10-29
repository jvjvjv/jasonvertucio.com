## Why

The site owns its whole login stack (Fortify password, passkeys, passwordless TOTP, local 2FA) and keeps a separate credential for every person. https://bspdx.com is the owner's own identity domain, the home of the `bspdx/keystone` RBAC package, and already runs a Laravel Passport OAuth 2.0 authorization server used for SSO by other apps (e.g. Invoice Ninja). Making bspdx.com the **only** way to sign in gives one identity across bspdx properties, moves every credential and every MFA decision to one place, and lets this site stop storing, resetting and defending passwords, passkeys and TOTP secrets.

## What Changes

- Add "Sign in with bspdx.com": an OAuth 2.0 authorization-code flow with PKCE and `state` against bspdx.com's Passport server, reading the identity from its userinfo endpoint, ending in a normal Laravel web session.
- A first sign-in by a bspdx.com identity that is not linked here **creates a local user automatically** with no roles. Every bspdx.com user may get a local account.
- **No automatic linking between sites.** A bspdx.com identity is never attached to an existing local user by matching email. Existing local users (including the owner) are linked explicitly by the developer with an artisan command before cutover. An unlinked identity whose email already belongs to a local user is refused.
- **BREAKING — bspdx.com becomes the only sign-in method.** Removed: password login (`POST /login`), password registration, password reset, password change, passkey login and management, passwordless TOTP login, local two-factor enrollment/challenge, the login-method email check, and the `user:create` / `user:password` commands. MFA is entirely bspdx.com's responsibility.
- The security-downgrade audit (alerts when the last passkey or 2FA method is removed) is retired with the methods it watched.
- The login page becomes a single "Sign in with bspdx.com" control; the profile page drops its security sections and shows the linked bspdx.com identity.
- Outbound calls to bspdx.com are HTTPS-only, refuse redirects, and time out quickly.
- Prerequisite work on bspdx.com (client registration, a `userinfo` scope, MFA enforcement for all users) is done by another agent from `bspdx-com-instructions.md` in this change, coordinated by the developer. This repo does not modify bspdx.com.

## Capabilities

### New Capabilities
- `federated-login`: signing in through bspdx.com (flow, code exchange and userinfo retrieval, account creation, explicit linking, session creation, failure handling) as the sole sign-in method.

### Modified Capabilities
- `security-method-removal-audit`: retired. Its requirements are removed because passkeys and local two-factor no longer exist on this site.

## Impact

- New: federated login controller, `federated_identities` table + `FederatedIdentity` model (user id, provider, subject, email at link), `user:link-bspdx` command, `services.bspdx` config, `/auth/bspdx/redirect` and `/auth/bspdx/callback` routes.
- Removed or rewritten: `resources/views/auth/login.blade.php`, `resources/views/profile/*` security components, `routes/passkeys.php`, `PasskeyController`, `PasskeyService`, `PasswordlessLoginController`, `LoginMethodsController`, `ConfirmPasswordController`, Fortify features and routes (logout is kept), the `UserSecurityMethodRemoved` event and its listeners and mail, `User` auth-preference helpers, `ChangeUserPasswordCommand`, `CreateUserCommand`.
- Data: password, 2FA, passkey and auth-preference columns/tables are left in place (unused) for a later cleanup migration; no data is dropped in this change.
- Dependencies: no new package (the OAuth flow uses Laravel's `Http` client); `spatie/laravel-passkeys` is uninstalled.
- Tests covering removed features are deleted only with the developer's approval (project rule).
- Docs: `.claude/context/auth.md`, `CLAUDE.md` (auth bullets, user commands).
- Operational: if bspdx.com is down, nobody can sign in. Existing sessions and Sanctum API tokens are unaffected by the cutover.
