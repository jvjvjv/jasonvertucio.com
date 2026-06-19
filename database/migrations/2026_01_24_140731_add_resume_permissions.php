<?php

use Illuminate\Database\Migrations\Migration;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use BSPDX\Keystone\Models\KeystoneRole as Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached permissions
        app(\BSPDX\Keystone\Services\PermissionRegistrar::class)->forgetCachedPermissions();

        // Create resume permissions
        $permissions = [
            'read-resume',
            'save-resume',
            'edit-resume',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create new roles
        $resumeViewer = Role::firstOrCreate(['name' => 'Resume Viewer', 'guard_name' => 'web']);
        $resumeViewer->givePermissionTo('read-resume');

        $recruiter = Role::firstOrCreate(['name' => 'Recruiter', 'guard_name' => 'web']);
        $recruiter->givePermissionTo(['read-resume', 'save-resume']);

        // Add all resume permissions to admin and super-admin
        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo($permissions);
        }

        $superAdmin = Role::where('name', 'super-admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($permissions);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(\BSPDX\Keystone\Services\PermissionRegistrar::class)->forgetCachedPermissions();

        // Remove permissions (roles will automatically lose them)
        Permission::whereIn('name', ['read-resume', 'save-resume', 'edit-resume'])->delete();

        // Remove roles
        Role::where('name', 'Resume Viewer')->delete();
        Role::where('name', 'Recruiter')->delete();
    }
};
