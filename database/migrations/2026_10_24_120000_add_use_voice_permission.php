<?php

use BSPDX\Keystone\Models\KeystonePermission as Permission;
use BSPDX\Keystone\Services\PermissionRegistrar;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The permission is deliberately assigned to no role: voice is for the
     * site owner, and it is granted on its own from Roles & Permissions.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(
            ['name' => 'use-voice', 'guard_name' => 'web'],
            [
                'title' => 'Use voice',
                'description' => 'Dictate into chat, hear assistant replies read aloud and proofread cover letters and targeted resumes by ear.',
            ],
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', 'use-voice')->delete();
    }
};
