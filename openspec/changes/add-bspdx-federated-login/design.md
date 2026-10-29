## Context

Current auth (see `.claude/context/auth.md`): Fortify drives password login, password reset, registration, password change and 2FA management (with `confirmPassword => true`, so 2FA routes sit behind `password.confirm`). Spatie Passkeys (`routes/passkeys.php`, `PasskeyController`, `PasskeyService`) and `PasswordlessLoginController` provide passkey and TOTP-only sign-in. The login page is a two-step flow: `LoginMethodsController::check` (`POST /login/check-email`) reports per-user methods from `User::getAvailableAuthMethods()` and drives the JS in `login.blade.php`. The profile page (`ProfileController`, `resources/views/profile/*`) manages auth preferences, 2FA and passkeys. Removing the last passkey or 2FA method raises `UserSecurityMethodRemoved`, handled by `LogSecurityAuditEntry` and `AlertUserOfSecurityDowngrade` (spec `security-method-removal-audit`). Logout is `POST /logout` via Fortify (spec `session-logout`). Canvas authenticates through the host `web` guard (`CANVAS_GUARD` default `web`, spec `canvas-v7-blog-platform-integration`). Users have a `char(36)` UUID `id`, a unique `email` (collation `utf8mb4_unicode_ci`), a NOT NULL `name` and `password`. Sanctum tokens back the MCP endpoint.

Provider facts (verified in the bspdx.com repo, `~/Code/@bspdx/bspdx.com`): Laravel Passport v13 OAuth 2.0 server, standard `/oauth/authorize` and `/oauth/token`, custom authorize view, admin UI at `/sso-clients` that creates authorization-code clients with HTTPS redirect URIs. Not OIDC: no ID token, JWKS or discovery. Identity comes from `GET /api/oauth/userinfo` (`auth:api`), returning `{ id, email, email_verified, name }`; `id` is the user's UUID and `name` is currently the email. Access tokens live 15 days, refresh tokens 30. No Passport scopes are defined, so any token grants full `auth:api` access. Fortify on bspdx.com has registration and email verification off, TOTP 2FA available but optional per user. Users carry a `tenant_id` and Passport serves other clients.

Decisions made by the developer: bspdx.com is the only sign-in method (no fallback); no local second factor — bspdx.com enforces all MFA; no automatic linking between sites; every bspdx.com user may get a local account; `email_verified` quality is bspdx.com's concern; deprovisioning gaps, rollback lockout and silent re-login after logout are accepted; required bspdx.com changes are specified for another agent rather than made here.

## Goals / Non-Goals

**Goals:**
- Authorization-code + PKCE login against bspdx.com producing a normal session, as the only way to sign in.
- Auto-create unlinked identities; link existing local users only by explicit developer action.
- Remove every local credential path and the UI and code that served it.

**Non-Goals:**
- Making changes in the bspdx.com repo. Required changes are written as instructions for another agent (`bspdx-com-instructions.md`).
- OpenID Connect, role mapping from bspdx.com claims, tenant filtering, single logout, refresh-token use, revoking bspdx.com tokens.
- Dropping legacy auth columns/tables (`password` stays NOT NULL with a random hash; 2FA, passkey and preference data are left for a later cleanup change).
- Changing `session-logout` behavior or Sanctum/MCP token behavior.

## Decisions

**Protocol and client.** OAuth 2.0 authorization-code grant with PKCE (S256) and `state`, confidential client. Endpoints derive from a configured `base_url`: `/oauth/authorize`, `/oauth/token`, `/api/oauth/userinfo`. Request the `userinfo` scope once bspdx.com defines it. After the code exchange, call userinfo server-to-server with the access token just received and take the identity only from that response. Trust rests on TLS to a fixed base URL and on the token coming from this site's own exchange (bound by `state` and the PKCE verifier), never from the browser. The access token is used once and not stored. Client: no package. The flow is implemented directly on Laravel's `Http` client (PKCE generation, code exchange, userinfo), which keeps the no-redirect and timeout rules explicit and adds no dependency (decided at apply).

**Outbound HTTP hardening.** `base_url` must be `https://` (config validation refuses otherwise). Token and userinfo calls use `withoutRedirecting()`, a short connect/read timeout, and treat any non-2xx or non-JSON response as failure.

**Identity claims.** Subject = userinfo `id` (string). `email` is required; a response without `id` or `email` is refused. `email_verified` is not used for any decision (no email-based linking exists). Display name: userinfo `name` unless missing or equal to the email, in which case the email's local part.

**Identity table.** `federated_identities`: `id`, `foreignUuid('user_id')` → `users.id` cascade on delete, `provider` string, `subject` string, `email_at_link` string, timestamps; unique (`provider`, `subject`) and unique (`user_id`, `provider`) — one bspdx.com identity per local user. `User::federatedIdentities()` relation. `user:delete` removes the user's identities via the cascade.

**Account resolution on callback.**
1. Identity row for (`bspdx`, subject) exists → log in that user. If the userinfo email differs from the user's email and no other local user holds it, update the local email (and `email_at_link` is left as history); if another user holds it, keep the old email and log a warning.
2. Else, if any local user has the userinfo email (case-insensitive) → refuse. Show a generic "an account with this email already exists here; contact the site owner" message, log the subject and email. No link, no new user.
3. Else create a user (name per above, email, random unusable password hash, no roles), create the identity row, log in. Do not fire `Registered`.

**Explicit linking.** `php artisan user:link-bspdx {email} {subject}` links an existing local user to a bspdx.com subject (the UUID shown in bspdx.com's admin), refusing if either side is already linked. This is the only way an existing account — including the owner's admin account — gets attached. Run by the developer in each environment before cutover.

**New users have no roles.** Login alone never confers `manage-*` or admin access.

**Removing local credentials.** `config/fortify.php` features become empty (no registration, reset, password update, profile update or 2FA). Call `Fortify::ignoreRoutes()` and register `POST /logout` explicitly to Fortify's `AuthenticatedSessionController@destroy` under `web` + `auth`, so `session-logout` still holds and `POST /login`, `/register`, `/forgot-password`, `/reset-password`, `/user/password`, `/user/confirm-password`, `/two-factor-challenge` and `/user/two-factor-*` no longer exist. Remove `routes/passkeys.php` (its profile routes move to `routes/auth.php`), the passkey/TOTP/login-method/confirm-password controllers, the unrouted legacy `laravel/ui` auth controllers (`LoginController`, `RegisterController`, `ForgotPasswordController`, `ResetPasswordController`, `VerificationController`), the `App\Actions\Fortify` actions, `PasskeyService`, the 2FA challenge view, `User` auth-preference helpers (`getAvailableAuthMethods`, `canUsePasswordlessLogin`, `hasValidAuthConfiguration`, …), `ChangeUserPasswordCommand` and `CreateUserCommand`. `MakeUserCommand` is checked at apply: removed if it only creates password users. `spatie/laravel-passkeys` is uninstalled and the `TwoFactorAuthenticatable`/`InteractsWithPasskeys` traits and `HasPasskeys` contract are removed from `User` (approved at apply).

**Retiring the downgrade audit.** `UserSecurityMethodRemoved`, `LogSecurityAuditEntry`, `AlertUserOfSecurityDowngrade`, `DetectTwoFactorSecurityDowngrade`, `SecurityMethodRemovedMail`, its mail view, `SecurityMethodKind`, and the `SecurityAuditLogEntry` model and factory (whose `kind` cast needs the enum) are removed with their registrations. The `security_audit_log` table and its rows are kept.

**Login page.** `/login` renders one "Sign in with bspdx.com" control (a link to `/auth/bspdx/redirect`) plus the generic error area; no email field, no JS method discovery. `POST /login/check-email` is removed, which also removes its user-enumeration behavior. Guests hitting an `auth` route still redirect to `/login`.

**Profile page.** Drops auth preferences, 2FA setup and passkey management. Shows the linked bspdx.com identity (email, linked date) and a link to manage the account at bspdx.com. Tool-visibility preference stays.

**Session.** `Auth::login($user)` with session regeneration, redirect to the intended URL. Remember-me is not offered.

**Throttling.** A `federated-login` rate limiter keyed on the Cloudflare-aware client address (the existing `login` limiter keys on an email input the callback does not have), applied to both routes.

**Rollout in two deploys from one change.**
- *Phase A (additive):* migration, model, `user:link-bspdx`, config, redirect/callback routes, login button shown only when `services.bspdx.enabled` and config complete. Password login untouched. Used to test against bspdx.com and to link existing users.
- *Phase B (cutover):* removal of all local credential paths, new login and profile pages. Deployed only after bspdx.com prerequisites are done and every existing user who needs access is linked. After Phase B the flag only hides the button when config is incomplete; there is no path back to local login short of a revert.

**Failure handling.** Denied consent, `state` mismatch, failed code exchange (including PKCE rejection), userinfo failure or malformed body, unreachable provider, missing email, or an email collision end at `/login` with a generic error and no session; details go to the log only.

## Risks / Trade-offs

- **[Risk] Total dependence on bspdx.com.** If it is down or the owner's bspdx.com account is lost, nobody can sign in, including the admin. → Accepted by decision. Existing sessions continue; Sanctum tokens keep working for MCP.
- **[Risk] Security now equals bspdx.com's.** No local second factor; account takeover at bspdx.com (e.g. via its password reset) is takeover here. → bspdx.com must enforce MFA for all users (instructions §3). Cutover waits on it.
- **[Risk] No signed identity assertion.** → TLS to a fixed HTTPS base URL, no redirects followed, token only from this site's exchange, `state` + PKCE.
- **[Risk] Unscoped 15-day tokens on bspdx.com.** → bspdx.com adds a `userinfo` scope (instructions §2); this site discards the token immediately.
- **[Accepted] Deprovisioning.** A user disabled on bspdx.com keeps any live local session (default lifetime 120 minutes) and any Sanctum token until revoked here.
- **[Accepted] Silent re-login.** Passport skips consent when a valid token exists, so logging out here and clicking sign-in may log straight back in.
- **[Accepted] Rollback.** Reverting Phase B restores password login only for users who still know a password; auto-created users cannot sign in until it is redeployed.
- **[Accepted] Open sign-up across bspdx.com tenants.** Every bspdx.com user can get a local account with no roles.
- **[Risk] Email collisions.** A bspdx.com user whose email belongs to an unlinked local user is refused until the developer links them. → Pre-flight list of local users; link the ones who should keep their account.

## Migration Plan

1. bspdx.com prerequisites (another agent, `bspdx-com-instructions.md`): client registered, `userinfo` scope, MFA enforced for all users. Developer relays client id/secret.
2. Deploy Phase A with `services.bspdx.enabled=false`. Set credentials; enable in non-production and test the full flow.
3. In each environment, link existing users who need access: `php artisan user:link-bspdx {email} {subject}`. Confirm the owner's admin account is linked and can sign in through bspdx.com.
4. Deploy Phase B (developer-owned), with `services.bspdx.enabled=true`.
5. Later, separate change: cleanup migration for legacy auth columns/tables and package removal if not done here.

## Open Questions

- Client id/secret and the registered callback URLs (production and local dev) for this site's Passport client.
- Whether `MakeUserCommand` should be kept in any form.
