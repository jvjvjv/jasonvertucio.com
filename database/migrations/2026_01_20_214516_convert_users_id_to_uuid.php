<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if users.id is already a UUID (fresh install)
        $idColumn = DB::selectOne("SHOW COLUMNS FROM users WHERE Field = 'id'");
        if ($idColumn && str_contains($idColumn->Type, 'char')) {
            return;
        }

        // Store mapping of old IDs to new UUIDs
        $users = DB::table('users')->select('id')->get();
        $idMapping = [];

        foreach ($users as $user) {
            $idMapping[$user->id] = Str::uuid()->toString();
        }

        // 1. Dynamically drop all foreign key constraints referencing users table
        $foreignKeys = DB::select("
            SELECT CONSTRAINT_NAME, TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND REFERENCED_TABLE_NAME = 'users'
        ");

        foreach ($foreignKeys as $fk) {
            Schema::table($fk->TABLE_NAME, function (Blueprint $table) use ($fk) {
                $table->dropForeign($fk->CONSTRAINT_NAME);
            });
        }

        // 2. Add new UUID column to users
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        // 3. Populate UUIDs
        foreach ($idMapping as $oldId => $newUuid) {
            DB::table('users')->where('id', $oldId)->update(['uuid' => $newUuid]);
        }

        // 4. Update foreign key columns to char(36) and set new values
        // Comments table
        Schema::table('comments', function (Blueprint $table) {
            $table->char('user_id_new', 36)->nullable()->after('user_id');
            $table->char('fb_user_id_new', 36)->nullable()->after('fb_user_id');
        });

        foreach ($idMapping as $oldId => $newUuid) {
            DB::table('comments')->where('user_id', $oldId)->update(['user_id_new' => $newUuid]);
            DB::table('comments')->where('fb_user_id', $oldId)->update(['fb_user_id_new' => $newUuid]);
        }

        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropColumn('fb_user_id');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->renameColumn('user_id_new', 'user_id');
            $table->renameColumn('fb_user_id_new', 'fb_user_id');
        });

        // Passkeys table
        if (Schema::hasTable('passkeys')) {
            Schema::table('passkeys', function (Blueprint $table) {
                $table->char('authenticatable_id_new', 36)->nullable()->after('authenticatable_id');
            });

            foreach ($idMapping as $oldId => $newUuid) {
                DB::table('passkeys')->where('authenticatable_id', $oldId)->update(['authenticatable_id_new' => $newUuid]);
            }

            Schema::table('passkeys', function (Blueprint $table) {
                $table->dropColumn('authenticatable_id');
            });

            Schema::table('passkeys', function (Blueprint $table) {
                $table->renameColumn('authenticatable_id_new', 'authenticatable_id');
            });
        }

        // 5. Drop old id, rename uuid to id
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('uuid', 'id');
        });

        // 6. Make id primary key
        Schema::table('users', function (Blueprint $table) {
            $table->primary('id');
        });

        // 7. Recreate foreign key constraints
        Schema::table('comments', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('fb_user_id')->references('id')->on('users')->onDelete('set null');
        });

        if (Schema::hasTable('passkeys')) {
            Schema::table('passkeys', function (Blueprint $table) {
                $table->foreign('authenticatable_id', 'passkeys_authenticatable_fk')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not easily reversible
        // You would need to convert UUIDs back to integers
        throw new Exception('This migration cannot be reversed. Restore from backup if needed.');
    }
};
