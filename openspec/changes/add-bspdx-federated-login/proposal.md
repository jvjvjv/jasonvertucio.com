## Why

The site owns its whole login stack (Fortify password, passkeys, passwordless TOTP) and keeps a separate account for every person. https://bspdx.com is the owner's own identity domain and the home of the `bspdx/keystone` RBAC package. Using it as the identity provider gives one sign-in across bspdx properties and removes passwords as a credential this site has to store, reset and defend.

## What Changes

- Add "Sign in with bspdx.com": an OpenID Connect / OAuth 2.0 authorization-code flow with PKCE against bspdx.com, ending in a normal Laravel web session.
- A first-time federated login **creates a user automatically** (no roles; an admin grants Keystone roles). If a user with the same verified email already exists, that user is linked instead of duplicated.
- Store the link between a bspdx.com subject and a local user, so later logins match on the stable subject and not on a changeable email.
- **BREAKING:** password sign-in is replaced. The email/password login form and `POST /login` password authentication are removed or disabled, as is self-service registration with a password. Existing passkey and passwordless-TOTP sign-in for users who enabled them are kept as non-federated fallbacks (assumption, see design).
- Configuration for the provider (issuer, client id, secret, redirect URI, scopes) lives in `config/services.php` and `.env`; secrets are never read via `env()` outside config.
- bspdx.com is **not currently an OIDC provider** — `/.well-known/openid-configuration` returns 404 and the homepage describes no identity service. This change specifies what this site expects of the provider; standing the provider up on bspdx.com is a prerequisite outside this repo.

## Capabilities

### New Capabilities
- `federated-login`: signing in through bspdx.com (flow, token validation, account creation and linking, session creation, failure handling) and the removal of password sign-in in its favor.

### Modified Capabilities
(none — no existing main spec covers login; `session-logout` is unaffected.)

## Impact

- New: federated login controller, a `federated_identities` table + model (user id, provider, subject, last login), provider config in `config/services.php`, a login-page button and callback routes.
- `routes/web.php`, `resources/views/auth/login.blade.php`, `app/Providers/FortifyServiceProvider.php`, `config/fortify.php` (registration and password features), `LoginMethodsController` and `User::getAvailableAuthMethods()` (the `password` method).
- Dependency: a generic OIDC client is needed; `laravel/socialite` is not installed. Adding a package requires your approval at apply time (project rule).
- Docs: `.claude/context/auth.md` and `CLAUDE.md` auth bullets.
- Operational: if bspdx.com is down, only passkey/TOTP users can still sign in (see design Risks).
