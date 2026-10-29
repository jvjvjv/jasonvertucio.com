# Rollout checklist (developer-owned)

Nothing here is run by an agent. Production commands are the developer's.

The change lands as two deployable points on `feature/add-bspdx-federated-login`:

- **Phase A** — commit `feat(auth): add Sign in with bspdx.com (Phase A, additive)`. Adds the flow, `federated_identities`, `user:link-bspdx`. Password, passkey and TOTP sign-in still work.
- **Phase B** — the following commit(s). Removes every local sign-in method. bspdx.com becomes the only way in.

## 1. bspdx.com prerequisites

- [ ] The bspdx.com agent has finished `bspdx-com-instructions.md` §2–§5 and reported back (§6).
- [ ] Client id and secret received; the secret arrived by a secure channel.
- [ ] MFA enforcement is live on bspdx.com for every user.
- [ ] The `userinfo` scope exists and is required on `/api/oauth/userinfo`.

## 2. Deploy Phase A

- [ ] Deploy the Phase A commit with `BSPDX_LOGIN_ENABLED=false`.
- [ ] `php artisan migrate` (creates `federated_identities`).
- [ ] Set `BSPDX_BASE_URL`, `BSPDX_CLIENT_ID`, `BSPDX_CLIENT_SECRET`, `BSPDX_SCOPES=userinfo` (and `BSPDX_REDIRECT_URI` only if the callback URL differs from `APP_URL/auth/bspdx/callback`); `php artisan optimize:clear`.
- [ ] In a non-production environment, set `BSPDX_LOGIN_ENABLED=true` and sign in end to end with a test bspdx.com account.

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
- Update the Privacy Policy and Terms of Service, which still describe passwords, TOTP and passkeys stored on this site.
