<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add Canvas-specific columns to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 191)->nullable()->after('email');
            $table->text('summary')->nullable()->after('password');
            $table->string('avatar', 191)->nullable()->after('summary');
            $table->boolean('dark_mode')->nullable()->after('avatar');
            $table->boolean('digest')->nullable()->after('dark_mode');
            $table->string('locale', 191)->nullable()->after('digest');
            $table->tinyInteger('role')->nullable()->after('locale');
        });

        // Migrate data from canvas_users to users where emails match
        if (Schema::hasTable('canvas_users')) {
            $canvasUsers = DB::table('canvas_users')->get();

            foreach ($canvasUsers as $canvasUser) {
                DB::table('users')
                    ->where('email', $canvasUser->email)
                    ->update([
                        'username' => $canvasUser->username,
                        'summary' => $canvasUser->summary,
                        'avatar' => $canvasUser->avatar,
                        'dark_mode' => $canvasUser->dark_mode,
                        'digest' => $canvasUser->digest,
                        'locale' => $canvasUser->locale,
                        'role' => $canvasUser->role,
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'summary',
                'avatar',
                'dark_mode',
                'digest',
                'locale',
                'role',
            ]);
        });
    }
};
