## Context

Keystone (`bspdx/keystone`) provides the `KeystoneRole` and `KeystonePermission` Eloquent models plus a `role_has_permissions` pivot, but no admin UI — its own routes are never loaded (`config('keystone.load_routes')` is false) and its `RolePermissionController` is an unused package-internal JSON API. The permission catalogue already includes `view-roles`, `create-roles`, `edit-roles`, `delete-roles`, `view-permissions`, `create-permissions`, `edit-permissions`, `delete-permissions`, and `assign-permissions` (`database/seeders/AuthKitSeeder.php`), seeded but with no consumer. Tenancy is inactive in this single-tenant deployment, so `tenant_id` scoping is not a concern.

The app's existing admin pattern is Inertia + React, with permission-gated route groups (`can:<permission-name>` middleware) and controllers extending `BaseAdminController` (which mixes in `ProvidesAdminNavigation`). `SiteSettingsController` is the closest existing precedent — it already reads `KeystonePermission::orderBy('name')->pluck('name')` for a permission picker.

## Goals / Non-Goals

**Goals:**
- Let an admin with the appropriate permissions view, create, edit, and delete Keystone roles and permissions from a dashboard page.
- Let an admin assign/sync which permissions belong to a role from the same page.
- Reuse the existing `can:<permission-name>` gating convention, keyed to the pre-existing role/permission-management permissions.
- Prevent deleting a role or permission that is actively assigned without an explicit confirmation, since `can:` middleware elsewhere in the app depends on permission names continuing to exist.

**Non-Goals:**
- Assigning roles to individual users (already handled by `user:add-role` / `user:sync-roles` artisan commands and out of scope here).
- Multi-tenant role scoping (tenancy is inactive).
- Changing the Keystone package itself, or wiring up its own package routes/controller.
- Renaming permissions in a way that requires updating existing `can:` checks scattered across route files/controllers — permission names, once created, are treated as stable identifiers; editing a permission's `name` field is out of scope for the initial version (title/description are editable).

## Decisions

**1. New dedicated controller, not extending `SiteSettingsController`.**
Add `App\Http\Controllers\Admin\RolePermissionController`, extending `BaseAdminController`. Role/permission management is a distinct concern from site settings; a dedicated controller keeps routes and authorization cleanly scoped per permission rather than piggybacking on an unrelated controller.
Alternative considered: extend `SiteSettingsController`. Rejected — that controller's existing gate/purpose is unrelated, and mixing concerns would force it to check multiple permissions per action.

**2. One Inertia page with two tabs (Roles, Permissions), not two separate pages.**
Mirrors the existing tabbed admin editor pattern (resume editor) and keeps role/permission management discoverable as a single "Roles & Permissions" nav entry. The role tab includes an inline permission-assignment UI (checklist or multi-select) rather than a separate route.
Alternative considered: separate `/admin/roles` and `/admin/permissions` pages. Rejected as unnecessary route/page duplication for two closely related, small resources.

**3. Route/permission mapping — one middleware permission per action group.**
- `GET /admin/roles-permissions` → gated by `view-roles` (permissions list is visible to anyone who can view roles, since it's needed context; add `view-permissions` OR-check if Keystone/Laravel gate composition makes this awkward — simplest is to require both `view-roles` AND `view-permissions` be granted together via the seeded roles, since `admin`/`super-admin` already hold the full set).
- `POST /admin/roles` → `create-roles`; `PUT/PATCH /admin/roles/{role}` → `edit-roles`; `DELETE /admin/roles/{role}` → `delete-roles`.
- `POST /admin/permissions` → `create-permissions`; `PUT/PATCH /admin/permissions/{permission}` → `edit-permissions`; `DELETE /admin/permissions/{permission}` → `delete-permissions`.
- `PUT /admin/roles/{role}/permissions` (sync) → `assign-permissions`.
This mirrors the granularity already present in `AuthKitSeeder`, so no new permissions need to be created.

**4. Deletion guard implemented in the controller/Form Request, not a DB constraint.**
Before deleting a role, check `$role->users()->exists()` (and optionally `$role->permissions()->exists()` if the UI requires clearing permissions first) and return a validation error asking the admin to reassign/unassign first, rather than relying on FK constraints alone. This gives a clear user-facing message instead of a raw SQL error. Same approach for deleting a permission still attached to any role (`$permission->roles()->exists()`).
Alternative considered: allow cascade delete. Rejected — silently revoking a permission out from under live `can:` checks elsewhere in the app is a correctness risk the design explicitly wants surfaced to the admin.

**5. Validation via Form Requests, following existing convention.**
`StoreRoleRequest`/`UpdateRoleRequest` and `StorePermissionRequest`/`UpdatePermissionRequest`, validating `name` (required, unique within `roles`/`permissions` table respectively, slug-safe format) and optional `title`/`description`. `guard_name` defaults to `web` and is not exposed in the UI (no other guard is in use in this app).

## Risks / Trade-offs

- [Deleting/renaming a permission that's referenced by a hardcoded `can:<name>` check in route files silently breaks that gate] → Mitigation: block deletion while any role holds the permission; keep `name` immutable after creation (only `title`/`description` editable) to avoid rename-induced breakage. Document that permission names are a code-level contract.
- [Two admins editing the same role's permission set concurrently could race] → Mitigation: accept last-write-wins for `syncPermissions()`, consistent with how the rest of the admin panel (e.g. resume editor) already handles concurrent edits; not worth added complexity for a low-traffic internal admin tool.
- [Granting `create-roles`/`assign-permissions` to the wrong role could let an admin escalate privileges by creating a role with all permissions and assigning it to themselves] → Mitigation: out of scope for this change (existing `assignRole`/`syncRoles` on users is already gated by separate permissions via artisan-only commands); note as a follow-up if self-service user-role assignment is ever added to the UI.

## Migration Plan

One additive migration is required, discovered during implementation: `roles` and `permissions` carried only `id, name, guard_name, timestamps`, so the `title` and `description` fields that Keystone's models already declare fillable (and that `KeystoneRole::getDisplayNameAttribute()` reads) had no backing columns. Every write to them failed with `Unknown column 'title'`. `2026_08_30_150549_add_title_and_description_to_roles_and_permissions_tables` adds both as nullable columns, guarded by `Schema::hasColumn` so it is safe to re-run; `down()` drops them.

Note this repo runs tests against a separate `wink` database, so the migration must be applied to both (`php artisan migrate` and `DB_DATABASE=wink php artisan migrate`).

Otherwise deployment is: ship the new controller/routes/React page, run `npm run build`. No rollback data concerns since everything else is additive UI over existing tables.

## Open Questions

- Should the "view" gate require both `view-roles` and `view-permissions`, or should the page degrade gracefully (e.g. show only the Roles tab) if an admin has one but not the other? Defaulting to requiring both for the combined page; can be revisited if a role holds one but not the other in practice.
