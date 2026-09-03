<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StorePermissionRequest;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\SyncRolePermissionsRequest;
use App\Http\Requests\Admin\UpdatePermissionRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use BSPDX\Keystone\Models\KeystonePermission;
use BSPDX\Keystone\Models\KeystoneRole;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Admin CRUD over Keystone roles and permissions.
 *
 * Authorization is applied per-route in routes/admin-roles.php using the
 * `view|create|edit|delete-roles`, `view|create|edit|delete-permissions`, and
 * `assign-permissions` permissions seeded by AuthKitSeeder.
 */
class RolePermissionController extends BaseAdminController
{
    /**
     * GET /admin/roles-permissions
     */
    public function index(): InertiaResponse
    {
        return Inertia::render('roles-permissions/Index', [
            'roles' => $this->rolesPayload(),
            'permissions' => $this->permissionsPayload(),
        ]);
    }

    /**
     * POST /admin/roles
     */
    public function storeRole(StoreRoleRequest $request): RedirectResponse
    {
        $role = KeystoneRole::create($request->validated());

        return redirect()
            ->route('admin.roles-permissions.index')
            ->with('success', "Role \"{$role->name}\" created.");
    }

    /**
     * PUT /admin/roles/{role}
     */
    public function updateRole(UpdateRoleRequest $request, KeystoneRole $role): RedirectResponse
    {
        $role->update($request->validated());

        return redirect()
            ->route('admin.roles-permissions.index')
            ->with('success', "Role \"{$role->name}\" updated.");
    }

    /**
     * DELETE /admin/roles/{role}
     *
     * Refuses while the role is still assigned to users, so an admin has to
     * reassign them deliberately rather than silently stripping their access.
     */
    public function destroyRole(KeystoneRole $role): RedirectResponse
    {
        $userCount = $role->users()->count();

        if ($userCount > 0) {
            return redirect()
                ->route('admin.roles-permissions.index')
                ->with('error', "Role \"{$role->name}\" is assigned to {$userCount} user(s) and cannot be deleted. Remove it from those users first.");
        }

        $name = $role->name;
        $role->permissions()->detach();
        $role->delete();

        return redirect()
            ->route('admin.roles-permissions.index')
            ->with('success', "Role \"{$name}\" deleted.");
    }

    /**
     * PUT /admin/roles/{role}/permissions
     */
    public function syncRolePermissions(SyncRolePermissionsRequest $request, KeystoneRole $role): RedirectResponse
    {
        $role->syncPermissions($request->validated()['permissions']);

        return redirect()
            ->route('admin.roles-permissions.index')
            ->with('success', "Permissions updated for \"{$role->name}\".");
    }

    /**
     * POST /admin/permissions
     */
    public function storePermission(StorePermissionRequest $request): RedirectResponse
    {
        $permission = KeystonePermission::create($request->validated());

        return redirect()
            ->route('admin.roles-permissions.index')
            ->with('success', "Permission \"{$permission->name}\" created.");
    }

    /**
     * PUT /admin/permissions/{permission}
     */
    public function updatePermission(UpdatePermissionRequest $request, KeystonePermission $permission): RedirectResponse
    {
        $permission->update($request->validated());

        return redirect()
            ->route('admin.roles-permissions.index')
            ->with('success', "Permission \"{$permission->name}\" updated.");
    }

    /**
     * DELETE /admin/permissions/{permission}
     *
     * Refuses while any role still holds the permission, since deleting it
     * would silently break every `can:` check that references it.
     */
    public function destroyPermission(KeystonePermission $permission): RedirectResponse
    {
        $roleCount = $permission->roles()->count();

        if ($roleCount > 0) {
            return redirect()
                ->route('admin.roles-permissions.index')
                ->with('error', "Permission \"{$permission->name}\" is assigned to {$roleCount} role(s) and cannot be deleted. Remove it from those roles first.");
        }

        $name = $permission->name;
        $permission->delete();

        return redirect()
            ->route('admin.roles-permissions.index')
            ->with('success', "Permission \"{$name}\" deleted.");
    }

    /**
     * @return array<int, array{id: int, name: string, title: ?string, description: ?string, users_count: int, permissions: array<int, string>}>
     */
    private function rolesPayload(): array
    {
        return KeystoneRole::query()
            ->with('permissions:id,name')
            ->withCount('users')
            ->orderBy('name')
            ->get()
            ->map(fn (KeystoneRole $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'title' => $role->title,
                'description' => $role->description,
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('name')->all(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, title: ?string, description: ?string, roles_count: int}>
     */
    private function permissionsPayload(): array
    {
        return KeystonePermission::query()
            ->withCount('roles')
            ->orderBy('name')
            ->get()
            ->map(fn (KeystonePermission $permission): array => [
                'id' => $permission->id,
                'name' => $permission->name,
                'title' => $permission->title,
                'description' => $permission->description,
                'roles_count' => $permission->roles_count,
            ])
            ->all();
    }
}
