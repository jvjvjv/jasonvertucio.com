<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->char('user_id', 36)->nullable()->after('id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->char('fb_user_id', 36)->nullable()->after('user_id');
            $table->foreign('fb_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropForeign(['fb_user_id']);
            $table->dropColumn('fb_user_id');
        });
    }
};
