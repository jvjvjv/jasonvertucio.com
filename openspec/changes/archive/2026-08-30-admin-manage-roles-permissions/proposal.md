## Why

Keystone roles and permissions can currently only be created, edited, or assigned via artisan commands (`user:add-role`, `user:sync-roles`, etc.) or direct database access, requiring server/CLI access for any RBAC change. The permission catalogue already defines `view-roles`, `create-roles`, `edit-roles`, `delete-roles`, `view-permissions`, `create-permissions`, `edit-permissions`, `delete-permissions`, and `assign-permissions` (see `database/seeders/AuthKitSeeder.php`), but no admin UI exists to exercise them. Admins need a self-service dashboard page to manage roles and permissions without developer involvement.

## What Changes

- New admin page for managing Keystone roles and permissions, following the existing `routes/admin.php` + Inertia convention (e.g. `SiteSettingsController`).
- **Roles**: list, create, edit (name/title/description), delete, and assign/sync which permissions belong to a role.
- **Permissions**: list, create, edit (name/title/description), delete.
- Deleting a role or permission that is currently assigned/in use is blocked or requires confirmation (exact behavior decided in design.md) to avoid silently breaking existing `can:` gate checks elsewhere in the app.
- New admin navigation entry ("Roles & Permissions") added via `ProvidesAdminNavigation`.
- Gating uses the existing `can:<permission-name>` middleware pattern already used by `routes/admin.php` and `routes/admin-resume.php`, keyed to the pre-existing `view-roles`/`create-roles`/`edit-roles`/`delete-roles`/`view-permissions`/`create-permissions`/`edit-permissions`/`delete-permissions`/`assign-permissions` permissions.
- Out of scope: assigning roles to individual users (already covered by `user:add-role`/`user:sync-roles` artisan commands) and multi-tenant role scoping (tenancy is inactive in this app).

## Capabilities

### New Capabilities
- `admin-role-permission-management`: Admin-facing CRUD for Keystone roles and permissions, including assigning permissions to roles, gated by the existing Keystone permission catalogue.

### Modified Capabilities
(none — no existing spec covers role/permission administration)

## Impact

- New: `app/Http/Controllers/Admin/RolePermissionController.php` (or similarly named), extending `BaseAdminController`.
- New: Form Request classes for role/permission create & update validation.
- New: routes added to `routes/admin.php` (or a new `routes/admin-roles.php` following the `routes/admin-resume.php` pattern).
- New: Inertia React page(s) under `resources/js/pages/admin/roles-permissions/`.
- Modified: admin navigation (`ProvidesAdminNavigation` trait or its config) to add the new section.
- Uses existing models: `BSPDX\Keystone\Models\KeystoneRole`, `BSPDX\Keystone\Models\KeystonePermission`; the permission rows themselves already exist.
- **One migration was required after all** (discovered during implementation): the `roles` and `permissions` tables only had `id, name, guard_name, timestamps`, missing the `title`/`description` columns Keystone's models already declare fillable. Added as nullable columns — see `database/migrations/2026_08_30_150549_add_title_and_description_to_roles_and_permissions_tables.php`.
- No changes to the Keystone package itself; this app-level UI simply drives its existing Eloquent models.
