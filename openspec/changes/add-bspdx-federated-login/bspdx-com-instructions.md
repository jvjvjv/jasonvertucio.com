# bspdx.com work required for jasonvertucio.com federated sign-in

**Audience:** an agent working in the bspdx.com repository (`~/Code/@bspdx/bspdx.com`), with no prior context. The developer (Jason) relays messages between you and the agent working on jasonvertucio.com. Do not contact or modify the jasonvertucio.com repository.

**How to work:** bspdx.com uses OpenSpec (`openspec/`). Propose a change there (for example `prepare-sso-for-jasonvertucio`) that covers sections 2–4, implement it with tests, and report back using section 6. Follow that repo's own `CLAUDE.md` / `AGENTS.md` conventions.

## 1. Background

jasonvertucio.com (Laravel 13) is replacing all of its own sign-in methods — passwords, passkeys, authenticator codes and local two-factor — with "Sign in with bspdx.com". After the cutover, bspdx.com is the **only** way anyone signs in there, and jasonvertucio.com performs **no second factor of its own**. Every security property of that sign-in — credentials, MFA, account recovery — is now bspdx.com's.

How jasonvertucio.com will use bspdx.com (already decided; do not change this contract):

1. Browser is sent to `https://bspdx.com/oauth/authorize` with `response_type=code`, `client_id`, `redirect_uri`, `scope`, `state`, `code_challenge` and `code_challenge_method=S256`.
2. bspdx.com authenticates the user (including MFA) and redirects back to the callback with `code` and `state` as query parameters.
3. jasonvertucio.com's server POSTs to `https://bspdx.com/oauth/token` (`grant_type=authorization_code`, `code`, `redirect_uri`, `client_id`, `client_secret`, `code_verifier`).
4. It calls `GET https://bspdx.com/api/oauth/userinfo` with `Authorization: Bearer <access_token>` and takes the identity from the JSON. It does **not** follow redirects on either server-to-server call. The token is used once and discarded.

Current state you will find in this repo (verified):
- Laravel Passport v13, routes auto-registered; custom authorize view via `Passport::authorizationView(...)` in `AppServiceProvider`.
- Token lifetimes in `AppServiceProvider`: access 15 days, refresh 30 days, personal 6 months.
- No Passport scopes are defined, so any access token has full `auth:api` access.
- `routes/api.php`: `Route::middleware('auth:api')->get('/oauth/userinfo', [OAuthController::class, 'userInfo'])` returns `{ id, email, email_verified, name }`, with `name` set to the email.
- Admin SSO client management at `/sso-clients` (`SsoClientController`, `role:admin`) creates authorization-code clients with HTTPS redirect URIs. Invoice Ninja is an existing client and must keep working.
- Fortify: registration and email verification off; TOTP two-factor available but optional per user.

## 2. Register jasonvertucio.com as a client (operator action, no code)

Using the existing `/sso-clients` admin UI (not code, not a seeder):
- Name: `jasonvertucio.com`
- Redirect URIs (both):
  - `https://jasonvertucio.com/auth/bspdx/callback`
  - `https://localhost:8000/auth/bspdx/callback` (the developer's local environment)
- Confidential client (it has a secret).

The secret is shown once. Hand it to the developer through a secure channel. **Never** commit it, paste it into an OpenSpec artifact, or log it. Only the client id may appear in reports.

## 3. Add a narrow `userinfo` scope (code change)

Goal: a token obtained for sign-in can read identity and nothing else, without breaking Invoice Ninja.

1. Define a `userinfo` scope with Passport (in Passport v13, `Passport::tokensCan([...])` in a service provider — confirm the current API in the installed version's source/docs).
2. Protect `GET /api/oauth/userinfo` so it requires a token carrying `userinfo` (Passport's scope-checking middleware, e.g. `scope:userinfo` / `CheckToken`-style; confirm the v13 alias names and register them if needed).
3. Keep Invoice Ninja working: it currently requests no scope. Either set `userinfo` as Passport's default scope for requests that omit `scope` (confirm the v13 method, e.g. `Passport::defaultScopes(...)`), or update the Invoice Ninja client configuration to request `userinfo` — pick one, explain why, and verify an Invoice Ninja-style sign-in still reaches userinfo.
4. Any other existing or future `auth:api` route must require its own scope, so a `userinfo` token cannot call it. Today `routes/api.php` has only userinfo (plus an unauthenticated Twilio webhook); add a test that pins this.
5. Tests: a token with `userinfo` can read userinfo; a token without it gets 403; an unscoped/default request behaves as chosen in step 3.

Optional, report rather than do without the developer's agreement: the 15-day access-token lifetime is global. jasonvertucio.com discards its token immediately, so a shorter lifetime would reduce exposure; note whether it can be shortened without hurting Invoice Ninja.

## 4. Enforce MFA for every user (code change)

jasonvertucio.com will rely entirely on bspdx.com for MFA. Requirements:

1. A user who has not confirmed TOTP two-factor (`two_factor_confirmed_at` is null) cannot complete sign-in, and therefore cannot complete `/oauth/authorize`, until they enroll and confirm 2FA. Send them to enrollment after the password step instead of finishing the login. The existing `TwoFactorLoginResponse` and `PostLoginRedirectTest` are the places to look.
2. A user with 2FA confirmed always passes the Fortify two-factor challenge in the same login. Verify no path to an authenticated session skips it (password reset completion, remember-me, any custom login controller).
3. Applies to all users and all clients, including Invoice Ninja users. Report how many existing users lack confirmed 2FA so the developer can coordinate before this ships.
4. Tests: a user without 2FA who logs in and then requests `/oauth/authorize` ends at 2FA enrollment, not at the consent screen or the client callback; a user with 2FA must pass the challenge before `/oauth/authorize` succeeds.

## 5. Keep the userinfo contract stable

jasonvertucio.com depends on exactly:

| Field | Meaning | Required |
| --- | --- | --- |
| `id` | the bspdx.com user's UUID, stable forever, used as the subject | yes |
| `email` | current email | yes |
| `email_verified` | boolean | present (not relied on) |
| `name` | display name (the email today; a real name is welcome) | optional |

Do not rename or remove these fields or change `id`'s meaning. Add a contract test that pins this shape. Returning a real `name` is fine if it does not break Invoice Ninja.

Also confirm with a test that Passport v13 accepts `code_challenge` / `code_challenge_method=S256` from a **confidential** client and rejects the token exchange when `code_verifier` is wrong or missing.

## 6. Report back to the developer

Send the developer (to relay to the jasonvertucio.com agent):
- The client id. The secret goes separately, by a secure channel.
- The registered redirect URIs.
- The exact scope string to request (`userinfo`) and how unscoped requests now behave.
- Confirmation that MFA enforcement is deployed, and the count of users who had to enroll.
- Confirmation of the userinfo shape and the PKCE test result.
- Any deviation from these instructions, and why.

## 7. Do not

- Do not change the authorize/token/userinfo paths or the userinfo field names.
- Do not break Invoice Ninja's existing sign-in.
- Do not commit or log client secrets.
- Do not add OpenID Connect or role/claim mapping for jasonvertucio.com. Neither is needed.

## Ordering with jasonvertucio.com

Sections 2–5 must be deployed on bspdx.com before jasonvertucio.com's cutover (its "Phase B"). jasonvertucio.com's additive "Phase A" can be developed in parallel and tested against bspdx.com once section 2 is done.
