# Rollout checklist (developer-owned)

Nothing here is run by an agent. Production commands are the developer's.

The change lands as two deployable points on `feature/add-bspdx-federated-login`:

- **Phase A** — commit `feat(auth): add Sign in with bspdx.com (Phase A, additive)`. Adds the flow, `federated_identities`, `user:link-bspdx`. Password, passkey and TOTP sign-in still work.
- **Phase B** — the following commit(s). Removes every local sign-in method. bspdx.com becomes the only way in.

## 1. bspdx.com prerequisites

- [x] The bspdx.com agent has finished `bspdx-com-instructions.md` §2–§5 and reported back (§6) — 2026-10-30.
- [x] Client id received: `01a18ce4-7bd7-7166-892e-c9f562fb695f`.
- [ ] Client secret received by a secure channel (never committed, never pasted into chat).
- [x] MFA enforcement is live on bspdx.com for every user.
- [x] The `userinfo` scope exists and is required on `/api/oauth/userinfo`.

## 2. Deploy Phase A

- [ ] Deploy the Phase A commit with `BSPDX_LOGIN_ENABLED=false`.
- [ ] `php artisan migrate` (creates `federated_identities`).
- [ ] Set `BSPDX_BASE_URL`, `BSPDX_CLIENT_ID`, `BSPDX_CLIENT_SECRET`, `BSPDX_SCOPES=userinfo` (and `BSPDX_REDIRECT_URI` only if the callback URL differs from `APP_URL/auth/bspdx/callback`); `php artisan optimize:clear`.
- [ ] In a non-production environment, set `BSPDX_LOGIN_ENABLED=true` and sign in end to end with a test bspdx.com account. Expect bspdx.com's consent screen on the first sign-in only.

## 3. Link existing accounts (each environment)

- [ ] `php artisan user:list` — note every account that must keep access.
- [ ] For each: find its bspdx.com user id (UUID) in bspdx.com's admin, then `php artisan user:link-bspdx <email> <uuid>`.
- [ ] Link the owner's admin account, enable the flag, and confirm the owner signs in through bspdx.com and still has admin access.
- [ ] Any account not linked will be refused at sign-in if a bspdx.com user presents the same email, and cannot sign in by any other method after Phase B.

## 4. Deploy Phase B

- [ ] `composer install` (removes `spatie/laravel-passkeys`); in the `jv-app` container, its own vendor volume needs `composer install` too.
- [ ] `npm run build`, `php artisan optimize:clear`.
- [ ] `BSPDX_LOGIN_ENABLED=true`.
- [ ] Restart the queue worker (`sudo supervisorctl restart <program>`), per CLAUDE.md § Queues.
- [ ] Smoke test: `/login` shows "Continue to bspdx.com"; `POST /login` is 405; owner signs in; `/profile` shows "Signs in with bspdx.com"; logout works.

## Rollback

Reverting Phase B restores password login only for accounts that still know a password; accounts created through bspdx.com cannot sign in until Phase B is redeployed. Turning the flag off after Phase B leaves nobody able to sign in.

## Later

- Cleanup migration for legacy auth columns, the `passkeys` table and `security_audit_log`.
- The Privacy Policy and Terms of Service updates ship with Phase B; set their "Last updated" date to the Phase B deploy date if it differs.
