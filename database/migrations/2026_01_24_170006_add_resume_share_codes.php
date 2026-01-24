<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create the manage-unauthenticated-viewers permission
        Permission::firstOrCreate(['name' => 'manage-unauthenticated-viewers', 'guard_name' => 'web']);

        // Add permission to admin and super-admin roles
        $admin = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        if ($admin) {
            $admin->givePermissionTo('manage-unauthenticated-viewers');
        }

        $superAdmin = Role::where('name', 'super-admin')->where('guard_name', 'web')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo('manage-unauthenticated-viewers');
        }

        // Create resume_share_codes table
        Schema::create('resume_share_codes', function (Blueprint $table) {
            $table->string('id', 6)->primary();
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Create resume_views table
        Schema::create('resume_views', function (Blueprint $table) {
            $table->id();
            $table->string('share_code_id', 6);
            $table->string('ip_address', 45); // Max IPv6 length
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('share_code_id')
                ->references('id')
                ->on('resume_share_codes')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        Schema::dropIfExists('resume_views');
        Schema::dropIfExists('resume_share_codes');

        // Remove permission
        Permission::where('name', 'manage-unauthenticated-viewers')->delete();
    }
};
