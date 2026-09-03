<?php

namespace Tests\Feature;

use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use BSPDX\Keystone\Models\KeystoneRole as Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RolePermissionControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * The management permissions this feature is gated on, seeded by AuthKitSeeder.
     *
     * @var array<int, string>
     */
    private const MANAGEMENT_PERMISSIONS = [
        'view-roles',
        'create-roles',
        'edit-roles',
        'delete-roles',
        'view-permissions',
        'create-permissions',
        'edit-permissions',
        'delete-permissions',
        'assign-permissions',
    ];

    /**
     * A user holding every role/permission management permission.
     */
    private function admin(): User
    {
        return $this->userWith(self::MANAGEMENT_PERMISSIONS);
    }

    /**
     * A user holding only the named permissions.
     *
     * @param  array<int, string>  $permissions
     */
    private function userWith(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    /**
     * A user holding every management permission except the named one.
     */
    private function userWithout(string $permission): User
    {
        return $this->userWith(
            array_values(array_diff(self::MANAGEMENT_PERMISSIONS, [$permission]))
        );
    }

    private function makeRole(string $name): Role
    {
        return Role::create(['name' => $name, 'guard_name' => 'web']);
    }

    private function makePermission(string $name): Permission
    {
        return Permission::create(['name' => $name, 'guard_name' => 'web']);
    }

    // ------------------------------------------------------------------
    // Viewing
    // ------------------------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $this->get(route('admin.roles-permissions.index'))
            ->assertRedirect(route('login'));
    }

    public function test_index_displays_roles_and_permissions(): void
    {
        $this->actingAs($this->admin());

        $role = $this->makeRole('test-viewer-role');
        $role->givePermissionTo('view-roles');

        $this->get(route('admin.roles-permissions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('roles-permissions/Index', false)
                ->where('roles', fn ($roles) => collect($roles)
                    ->contains(fn ($r) => $r['name'] === 'test-viewer-role'
                        && $r['permissions'] === ['view-roles']
                        && $r['users_count'] === 0))
                ->where('permissions', fn ($permissions) => collect($permissions)
                    ->contains(fn ($p) => $p['name'] === 'view-roles'))
            );
    }

    public function test_index_requires_view_roles_permission(): void
    {
        $this->actingAs($this->userWithout('view-roles'));

        $this->get(route('admin.roles-permissions.index'))
            ->assertForbidden();
    }

    public function test_index_requires_view_permissions_permission(): void
    {
        $this->actingAs($this->userWithout('view-permissions'));

        $this->get(route('admin.roles-permissions.index'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Roles
    // ------------------------------------------------------------------

    public function test_can_create_a_role(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.roles.store'), [
            'name' => 'test-new-role',
            'title' => 'Test New Role',
            'description' => 'Created by a test.',
        ])->assertRedirect(route('admin.roles-permissions.index'));

        $this->assertDatabaseHas('roles', [
            'name' => 'test-new-role',
            'title' => 'Test New Role',
            'guard_name' => 'web',
        ]);
    }

    public function test_creating_a_role_rejects_a_duplicate_name(): void
    {
        $this->actingAs($this->admin());
        $this->makeRole('test-duplicate-role');

        $this->post(route('admin.roles.store'), ['name' => 'test-duplicate-role'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Role::where('name', 'test-duplicate-role')->count());
    }

    public function test_creating_a_role_requires_create_roles_permission(): void
    {
        $this->actingAs($this->userWithout('create-roles'));

        $this->post(route('admin.roles.store'), ['name' => 'test-forbidden-role'])
            ->assertForbidden();

        $this->assertDatabaseMissing('roles', ['name' => 'test-forbidden-role']);
    }

    public function test_can_update_a_role(): void
    {
        $this->actingAs($this->admin());
        $role = $this->makeRole('test-editable-role');

        $this->put(route('admin.roles.update', $role), [
            'title' => 'Renamed Title',
            'description' => 'Updated description.',
        ])->assertRedirect(route('admin.roles-permissions.index'));

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'test-editable-role',
            'title' => 'Renamed Title',
        ]);
    }

    public function test_updating_a_role_cannot_change_its_name(): void
    {
        $this->actingAs($this->admin());
        $role = $this->makeRole('test-immutable-role');

        $this->put(route('admin.roles.update', $role), [
            'name' => 'test-renamed-role',
            'title' => 'Still Fine',
        ])->assertRedirect(route('admin.roles-permissions.index'));

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'test-immutable-role',
        ]);
    }

    public function test_updating_a_role_requires_edit_roles_permission(): void
    {
        $this->actingAs($this->userWithout('edit-roles'));
        $role = $this->makeRole('test-protected-role');

        $this->put(route('admin.roles.update', $role), ['title' => 'Nope'])
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'title' => null]);
    }

    public function test_can_delete_an_unused_role(): void
    {
        $this->actingAs($this->admin());
        $role = $this->makeRole('test-deletable-role');

        $this->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles-permissions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_cannot_delete_a_role_assigned_to_a_user(): void
    {
        $this->actingAs($this->admin());
        $role = $this->makeRole('test-in-use-role');

        $member = User::factory()->create();
        $member->assignRole('test-in-use-role');

        $this->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles-permissions.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_deleting_a_role_requires_delete_roles_permission(): void
    {
        $this->actingAs($this->userWithout('delete-roles'));
        $role = $this->makeRole('test-undeletable-role');

        $this->delete(route('admin.roles.destroy', $role))
            ->assertForbidden();

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    // ------------------------------------------------------------------
    // Permissions
    // ------------------------------------------------------------------

    public function test_can_create_a_permission(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.permissions.store'), [
            'name' => 'test-new-permission',
            'title' => 'Test New Permission',
        ])->assertRedirect(route('admin.roles-permissions.index'));

        $this->assertDatabaseHas('permissions', [
            'name' => 'test-new-permission',
            'guard_name' => 'web',
        ]);
    }

    public function test_creating_a_permission_rejects_a_duplicate_name(): void
    {
        $this->actingAs($this->admin());
        $this->makePermission('test-duplicate-permission');

        $this->post(route('admin.permissions.store'), ['name' => 'test-duplicate-permission'])
            ->assertSessionHasErrors('name');

        $this->assertSame(
            1,
            Permission::where('name', 'test-duplicate-permission')->count()
        );
    }

    public function test_creating_a_permission_rejects_a_non_kebab_case_name(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.permissions.store'), ['name' => 'Not Kebab Case'])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('permissions', ['name' => 'Not Kebab Case']);
    }

    public function test_creating_a_permission_requires_create_permissions_permission(): void
    {
        $this->actingAs($this->userWithout('create-permissions'));

        $this->post(route('admin.permissions.store'), ['name' => 'test-forbidden-permission'])
            ->assertForbidden();

        $this->assertDatabaseMissing('permissions', ['name' => 'test-forbidden-permission']);
    }

    public function test_can_update_a_permission(): void
    {
        $this->actingAs($this->admin());
        $permission = $this->makePermission('test-editable-permission');

        $this->put(route('admin.permissions.update', $permission), [
            'title' => 'Editable Permission',
            'description' => 'Updated description.',
        ])->assertRedirect(route('admin.roles-permissions.index'));

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'name' => 'test-editable-permission',
            'title' => 'Editable Permission',
        ]);
    }

    public function test_updating_a_permission_cannot_change_its_name(): void
    {
        $this->actingAs($this->admin());
        $permission = $this->makePermission('test-immutable-permission');

        $this->put(route('admin.permissions.update', $permission), [
            'name' => 'test-renamed-permission',
            'title' => 'Still Fine',
        ])->assertRedirect(route('admin.roles-permissions.index'));

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'name' => 'test-immutable-permission',
        ]);
    }

    public function test_updating_a_permission_requires_edit_permissions_permission(): void
    {
        $this->actingAs($this->userWithout('edit-permissions'));
        $permission = $this->makePermission('test-protected-permission');

        $this->put(route('admin.permissions.update', $permission), ['title' => 'Nope'])
            ->assertForbidden();

        $this->assertDatabaseHas('permissions', [
            'id' => $permission->id,
            'title' => null,
        ]);
    }

    public function test_can_delete_an_unused_permission(): void
    {
        $this->actingAs($this->admin());
        $permission = $this->makePermission('test-deletable-permission');

        $this->delete(route('admin.permissions.destroy', $permission))
            ->assertRedirect(route('admin.roles-permissions.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
    }

    public function test_cannot_delete_a_permission_held_by_a_role(): void
    {
        $this->actingAs($this->admin());
        $permission = $this->makePermission('test-in-use-permission');
        $role = $this->makeRole('test-holder-role');
        $role->givePermissionTo('test-in-use-permission');

        $this->delete(route('admin.permissions.destroy', $permission))
            ->assertRedirect(route('admin.roles-permissions.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }

    public function test_deleting_a_permission_requires_delete_permissions_permission(): void
    {
        $this->actingAs($this->userWithout('delete-permissions'));
        $permission = $this->makePermission('test-undeletable-permission');

        $this->delete(route('admin.permissions.destroy', $permission))
            ->assertForbidden();

        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }

    // ------------------------------------------------------------------
    // Assigning permissions to a role
    // ------------------------------------------------------------------

    public function test_syncing_permissions_replaces_the_previous_set(): void
    {
        $this->actingAs($this->admin());

        $role = $this->makeRole('test-sync-role');
        $this->makePermission('test-sync-old');
        $this->makePermission('test-sync-new');
        $role->givePermissionTo('test-sync-old');

        $this->put(route('admin.roles.permissions.sync', $role), [
            'permissions' => ['test-sync-new'],
        ])->assertRedirect(route('admin.roles-permissions.index'));

        $this->assertSame(
            ['test-sync-new'],
            $role->fresh()->permissions->pluck('name')->all()
        );
    }

    public function test_syncing_an_empty_set_clears_every_permission(): void
    {
        $this->actingAs($this->admin());

        $role = $this->makeRole('test-clear-role');
        $this->makePermission('test-clear-permission');
        $role->givePermissionTo('test-clear-permission');

        $this->put(route('admin.roles.permissions.sync', $role), [
            'permissions' => [],
        ])->assertRedirect(route('admin.roles-permissions.index'));

        $this->assertCount(0, $role->fresh()->permissions);
    }

    public function test_syncing_rejects_an_unknown_permission(): void
    {
        $this->actingAs($this->admin());
        $role = $this->makeRole('test-unknown-sync-role');

        $this->put(route('admin.roles.permissions.sync', $role), [
            'permissions' => ['test-does-not-exist'],
        ])->assertSessionHasErrors('permissions.0');

        $this->assertCount(0, $role->fresh()->permissions);
    }

    public function test_syncing_permissions_requires_assign_permissions_permission(): void
    {
        $this->actingAs($this->userWithout('assign-permissions'));

        $role = $this->makeRole('test-unassignable-role');
        $this->makePermission('test-unassignable-permission');

        $this->put(route('admin.roles.permissions.sync', $role), [
            'permissions' => ['test-unassignable-permission'],
        ])->assertForbidden();

        $this->assertCount(0, $role->fresh()->permissions);
    }
}
