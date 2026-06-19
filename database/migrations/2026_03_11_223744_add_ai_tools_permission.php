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
        app(\BSPDX\Keystone\Services\PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'manage-ai-tools', 'guard_name' => 'web']);

        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo('manage-ai-tools');
        }

        $superAdmin = Role::where('name', 'super-admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo('manage-ai-tools');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(\BSPDX\Keystone\Services\PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', 'manage-ai-tools')->delete();
    }
};
