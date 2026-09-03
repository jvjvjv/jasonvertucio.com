<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keystone's KeystoneRole/KeystonePermission models already declare `title` and
 * `description` as fillable (and KeystoneRole::getDisplayNameAttribute() reads
 * `title`), but the tables in this app predate that and were never given the
 * columns. The admin roles & permissions editor needs them to store the
 * human-facing label and blurb for each role and permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (! Schema::hasColumn('roles', 'title')) {
                $table->string('title')->nullable()->after('guard_name');
            }
            if (! Schema::hasColumn('roles', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
        });

        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'title')) {
                $table->string('title')->nullable()->after('guard_name');
            }
            if (! Schema::hasColumn('permissions', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['title', 'description']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['title', 'description']);
        });
    }
};
