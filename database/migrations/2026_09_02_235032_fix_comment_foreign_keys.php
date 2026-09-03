<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Two constraints on `comments` are wrong rather than merely unhelpful:
     *
     * `fb_user_id` carries a foreign key to `users.id`. A Facebook user id is a
     * numeric string issued by Facebook, not a site user UUID, so the constraint
     * rejects every row it exists to hold.
     *
     * `parent_id` cascades with `set null`. A hard delete silently promotes every
     * reply to the root of the thread and leaves the denormalized `depth` column
     * describing a position the row no longer occupies.
     */
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign('comments_fb_user_id_foreign');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->string('fb_user_id', 64)->nullable()->change();
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign('comments_parent_id_foreign');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('comments')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('comments')->onDelete('set null');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->char('fb_user_id', 36)->nullable()->change();
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->foreign('fb_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }
};
