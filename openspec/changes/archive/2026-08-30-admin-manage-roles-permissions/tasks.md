## 1. Routing & Authorization

- [x] 1.1 Add `GET /admin/roles-permissions` route gated by `can:view-roles` and `can:view-permissions`, named `admin.roles-permissions.index`
- [x] 1.2 Add `POST /admin/roles` (`can:create-roles`), `PUT/PATCH /admin/roles/{role}` (`can:edit-roles`), `DELETE /admin/roles/{role}` (`can:delete-roles`)
- [x] 1.3 Add `PUT /admin/roles/{role}/permissions` (`can:assign-permissions`) for syncing a role's permission set
- [x] 1.4 Add `POST /admin/permissions` (`can:create-permissions`), `PUT/PATCH /admin/permissions/{permission}` (`can:edit-permissions`), `DELETE /admin/permissions/{permission}` (`can:delete-permissions`)
- [x] 1.5 Register these routes in `routes/admin.php` (or a new `routes/admin-roles.php` if kept separate, following the `routes/admin-resume.php` precedent)

## 2. Form Requests

- [x] 2.1 Create `StoreRoleRequest` — validates `name` (required, unique in `roles`, slug-safe), optional `title`/`description`
- [x] 2.2 Create `UpdateRoleRequest` — validates `title`/`description` only (no `name` field accepted)
- [x] 2.3 Create `StorePermissionRequest` — validates `name` (required, unique in `permissions`, slug-safe), optional `title`/`description`
- [x] 2.4 Create `UpdatePermissionRequest` — validates `title`/`description` only (no `name` field accepted)
- [x] 2.5 Create `SyncRolePermissionsRequest` — validates an array of existing permission names

## 3. Controller

- [x] 3.1 Create `App\Http\Controllers\Admin\RolePermissionController` extending `BaseAdminController`
- [x] 3.2 Implement `index()` — loads all roles (with permission counts) and all permissions, renders the Inertia page
- [x] 3.3 Implement `storeRole()` / `updateRole()` using `KeystoneRole::create()` / `->update()`, `guard_name` defaulted to `web`
- [x] 3.4 Implement `destroyRole()` — checks `$role->users()->exists()` and returns a validation error if true, otherwise deletes
- [x] 3.5 Implement `storePermission()` / `updatePermission()` using `KeystonePermission::create()` / `->update()`
- [x] 3.6 Implement `destroyPermission()` — checks `$permission->roles()->exists()` and returns a validation error if true, otherwise deletes
- [x] 3.7 Implement `syncRolePermissions()` — calls `$role->syncPermissions($request->validated('permissions'))`
- [x] 3.8 Add a "Roles & Permissions" entry to the admin navigation (`ProvidesAdminNavigation` / its config)

## 4. Frontend (Inertia + React)

- [x] 4.1 Create `resources/js/pages/admin/roles-permissions/Index.tsx` (or `.jsx`, matching existing convention) with Roles and Permissions tabs
- [x] 4.2 Build Roles tab: list, create form/modal, edit form/modal (title/description only), delete action with confirmation, inline permission-assignment checklist per role
- [x] 4.3 Build Permissions tab: list, create form/modal, edit form/modal (title/description only), delete action with confirmation
- [x] 4.4 Surface backend validation errors (duplicate name, role/permission in use) in the relevant form/modal
- [x] 4.5 Run `npm run build` and manually verify the page in the browser (build succeeded; browser verification confirmed by the developer 2026-08-30)

## 6. Schema gap found during implementation

- [x] 6.1 Add migration `add_title_and_description_to_roles_and_permissions_tables` — the `roles`/`permissions` tables lacked the `title`/`description` columns that Keystone's models already declare fillable
- [x] 6.2 Run the migration against both the app DB (`jasonvertucio`) and the test DB (`wink`)

## 5. Tests

- [x] 5.1 Feature test: authorized user can view the roles-permissions page; unauthorized user gets 403
- [x] 5.2 Feature test: create/edit/delete role happy paths, including duplicate-name rejection and in-use deletion block, each with an authorization-denied case
- [x] 5.3 Feature test: create/edit/delete permission happy paths, including duplicate-name rejection and in-use deletion block, each with an authorization-denied case
- [x] 5.4 Feature test: syncing a role's permissions replaces the previous set, with an authorization-denied case
- [x] 5.5 Run the full new test file(s) with `php artisan test --compact --filter=RolePermission`
