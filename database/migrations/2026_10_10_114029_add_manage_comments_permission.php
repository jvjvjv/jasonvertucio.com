<?php

use BSPDX\Keystone\Models\KeystonePermission as Permission;
use BSPDX\Keystone\Services\PermissionRegistrar;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The permission is deliberately assigned to no role: everyone who
     * moderates today already qualifies through `manage-blog`, and this one
     * exists to be granted on its own from Roles & Permissions.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(
            ['name' => 'manage-comments', 'guard_name' => 'web'],
            [
                'title' => 'Manage comments',
                'description' => 'Review the comment moderation queue and mark comments as spam or not spam. Grants nothing else in the blog admin.',
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', 'manage-comments')->delete();
    }
};
