<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces Canvas v7.2's own migration of the same filename
     * (vendor/austintoddj/canvas/database/migrations/
     * 2026_09_14_000000_create_canvas_media_tags_tables.php).
     *
     * The vendor migration declares `canvas_media_tags.user_id` as
     * `foreignId()` (unsignedBigInteger) with a foreign key to `users.id`.
     * This app's `App\Models\User` uses `HasUuids`, so `users.id` is
     * `char(36)` and MySQL rejects the constraint - the same mismatch
     * documented in 2026_09_08_160001_create_canvas_v7_tables.
     *
     * The filename is deliberately identical to the vendor's. The migrator
     * keys migration files by name and the application's own
     * `database/migrations` path is merged in last
     * (Illuminate\Database\Console\Migrations\BaseCommand::getMigrationPaths),
     * so this file is the one that runs and the vendor's is never loaded.
     * Do not rename it: under any other name the vendor migration runs too.
     *
     * The table shapes match the vendor's as of v7.2.1, with `user_id`
     * changed from foreignId() to foreignUuid().
     *
     * MySQL DDL is not transactional, so wherever the vendor migration was
     * already attempted it left `canvas_media_tags` behind with a bigint
     * `user_id`, no `user_id` index, no foreign key and no pivot table, and
     * recorded nothing in `migrations`. That leftover is repaired in place
     * rather than dropped.
     */
    public function up(): void
    {
        if (Schema::hasTable('canvas_media_tags')) {
            Schema::table('canvas_media_tags', function (Blueprint $table) {
                $table->uuid('user_id')->nullable()->change();
            });

            Schema::table('canvas_media_tags', function (Blueprint $table) {
                if (! Schema::hasIndex('canvas_media_tags', ['user_id'])) {
                    $table->index('user_id');
                }

                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        } else {
            Schema::create('canvas_media_tags', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug');
                $table->foreignUuid('user_id')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
                $table->unique('slug');
                $table->index('name');
                $table->index('created_at');
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        Schema::create('canvas_media_tag', function (Blueprint $table) {
            $table->uuid('media_id');
            $table->uuid('media_tag_id');
            $table->unique(['media_id', 'media_tag_id']);
            $table->index('media_tag_id');
            $table->foreign('media_id')->references('id')->on('canvas_media')->cascadeOnDelete();
            $table->foreign('media_tag_id')->references('id')->on('canvas_media_tags')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canvas_media_tag');
        Schema::dropIfExists('canvas_media_tags');
    }
};
