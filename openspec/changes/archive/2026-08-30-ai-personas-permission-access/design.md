## Context

`AiChatBot` (the AI Persona model, table `ai_personas`) currently gates access via `allowed_roles` (JSON array of Keystone role names) and `AiChatBot::allowsRole(?User $user)`, which calls `$user->hasAnyRole($allowedRoles)`. This is checked in four places: `CheckChatBotAccess` middleware (route-level 403), `RoleFilteredChatBotIndexPayload` (filters `/chats` listing), `RoleFilteredChatBotStatusResolver` (filters `/chats/statuses`), and `HostChatBotPagePayload` (exposes the restriction to the chat page for `BotAccessCard`). The admin editor (`AiChatBotController`) lets an admin pick zero or more `KeystoneRole`s per persona.

Every other authorization check in this codebase (`edit-resume`, `save-resume`, `read-resume`, `manage-unauthenticated-viewers`, admin nav visibility, `SiteSettingsController`'s per-nav-link `can`) is permission-based via Keystone's `$user->can($permission)`. `SiteSettingsController` in particular already implements the exact UI pattern this change needs: a single nullable permission name, picked from `KeystonePermission::pluck('name')`, validated with `in:` against the live permission list.

## Goals / Non-Goals

**Goals:**
- Replace role-based persona gating with permission-based gating, matching the `SiteSettingsController`/nav-link precedent (single nullable permission name, not a many-valued field).
- Preserve today's "no restriction configured = public" behavior.
- Keep the change mechanical and narrowly scoped: swap the authorization primitive, don't redesign persona management UX.

**Non-Goals:**
- Introducing a new Keystone permission specifically for this feature — admins reuse existing permissions (e.g. `manage-ai-tools`, or any custom permission they create in Keystone) the same way `SiteSettingsController` does.
- Migrating other Keystone role usage in this app (`User::isAdmin()`, `isEditor()`, etc. stay role-based; only persona access changes).
- Automatic, guaranteed-correct backfill from roles to permissions — role-to-permission is not a clean mapping (a role implies a set of permissions, not one), so backfill is best-effort and admins must review restricted personas after migrating.

## Decisions

**Single `required_permission` string column, not a permission array or a pivot table.**
Alternatives considered: (a) keep an array of permission names (`required_permissions`), requiring *all* or *any*; (b) a `persona_permission` pivot table. Rejected both — no existing feature in this app needs multi-permission gating, and `allowed_roles` being an array was already awkward (`allowsRole` matches *any*, which is a low bar that's easy to misconfigure). A single nullable string mirrors `SiteSettingsController`'s nav-link `can` field exactly, is simpler to validate, and is simpler for an admin to reason about ("this persona needs permission X" vs. "this persona needs any of these permissions").

**Rename `allowsRole()` → `allowsAccess()`, not keep the old name.**
The method's job changes from role evaluation to permission evaluation; keeping the name `allowsRole` would be actively misleading given the method body no longer touches roles at all.

**Rename `RoleFilteredChatBotIndexPayload`/`RoleFilteredChatBotStatusResolver` classes.**
Both classes' docblocks currently explain *why* a role concept exists in the host and not the package. Once the host model is permission-based, "Role" in the class name is wrong, not just imprecise — a future reader would reasonably (and incorrectly) assume Keystone role checks are still involved. Renaming avoids that trap. (Considered leaving the names alone to shrink the diff; rejected because the docblocks *require* rewriting anyway once the underlying mechanism changes, and a stale class name paired with a rewritten docblock is worse than either alone.)

**Migration: add-backfill-drop across three migrations, not one.**
Alternatives considered: a single migration that adds the column, backfills, and drops the old one in one step. Rejected — if the backfill logic needs a manual correction (likely, given role→permission isn't 1:1), an admin needs to inspect the intermediate state (both columns present) before the old data is gone. Three discrete migrations (add nullable `required_permission`; backfill via an artisan command or a data-migration step reviewed with the developer; drop `allowed_roles`) keep that inspection window available and each step independently reversible up until the drop.

## Risks / Trade-offs

- [Existing personas restricted by role lose that restriction's exact meaning once migrated] → Best-effort backfill maps each persona's `allowed_roles` to a `required_permission` only when a clear 1:1 role→permission convention exists (e.g. a role named identically to a permission, or a documented pairing); otherwise the persona is flagged (left with `required_permission = null`, i.e. now public) and the developer is notified per CLAUDE.md's "document the problem and continue" guidance rather than guessing at an equivalence that could silently over- or under-restrict access.
- [Renaming `RoleFilteredChatBot*` classes ripples into tests and any other reference] → Grep for the old class names as a task-completion check; the rename is mechanical (find/replace class name + namespace-relative `use` statements).
- [Frontend `allowed_roles: string[]` prop shape changes to `required_permission: string | null`, touching several components] → Each frontend touch point is a narrow, mechanical prop-shape change (array→nullable string); no component logic beyond display/labeling depends on the shape.

## Migration Plan

1. Add nullable `required_permission` column to `ai_personas` (migration).
2. Backfill: for each persona with non-empty `allowed_roles`, attempt to resolve an equivalent permission per the mapping rule above; leave `required_permission` null (public) where no clear equivalent exists, and log/flag those personas for the developer to review before deploying to production.
3. Update backend (`AiChatBot`, middleware, payload/resolver classes, controller, form requests) and frontend to read/write `required_permission`.
4. Update tests to use permission-based fixtures.
5. Once verified in the target environment, drop `allowed_roles` in a final migration.

Rollback: steps 1–4 are additive/parallel until step 5 runs, so rollback before the drop migration is a straightforward revert of the code changes (the old column and its data are untouched). After step 5, rollback requires restoring `allowed_roles` from a backup or re-deriving it from `required_permission`, which is lossy — treat step 5 as the point of no return and confirm backfill review is complete first.

## Open Questions

- ~~Is there an existing, documented role→permission convention...~~ **Resolved.** No such convention exists. Auditing production found personas restricted to the plain `user` role alone (`Richter`, `Beatrice`, `Skip`) — a case dev's data didn't have — with no permission meaning "any signed-in user." Rather than inventing a new Keystone permission or narrowing these to admin-only, `required_permission` now also accepts the literal value `"authenticated"` (see `AiChatBot::PERMISSION_AUTHENTICATED`), reusing the exact convention `SiteSettingsController` already established for nav-link `can` values. `allowsAccess()` treats it as "any logged-in user, no specific permission required." The backfill migration (`2026_08_30_150436_...`) classifies per persona instead of using one blanket value: any role holding `manage-ai-tools` (`admin`, `super-admin`) present → `manage-ai-tools`; otherwise `user` present → `authenticated`; anything else → left `null` and logged for manual review.
