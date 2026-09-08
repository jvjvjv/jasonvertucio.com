<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canvas v6 -> v7 upgrade, step 2 of 3 (see openspec/changes/migrate-canvas-v6-to-v7).
     *
     * Creates the full Canvas v7 table set ourselves rather than letting the
     * package's own migration run it. Two reasons that migration can't be
     * trusted here:
     *
     * 1. Its filename (2020_09_21_000000_create_canvas_tables) is identical
     *    between v6 and v7, and this app's `migrations` table already has
     *    that filename recorded from the original v6 install - Laravel will
     *    skip it regardless of which version's content is on disk. So it
     *    would never create v7's new tables (canvas_media, canvas_settings)
     *    even if it were safe to run.
     * 2. Its `user_id` foreign key columns are declared as `foreignId()`
     *    (unsignedBigInteger), assuming a Laravel-default auto-increment
     *    `users.id`. This app's `App\Models\User` uses `HasUuids`, so
     *    `users.id` is `char(36)` - a bigint FK to it fails outright.
     *
     * This migration is otherwise a byte-for-byte match of Canvas v7's own
     * table shapes (vendor/austintoddj/canvas/database/migrations/
     * 2020_09_21_000000_create_canvas_tables.php as of v7.0.1), with every
     * `user_id` column changed from foreignId() to foreignUuid(), and the
     * following columns widened from string() (VARCHAR(255)) to text():
     * canvas_posts.featured_image/featured_image_caption,
     * canvas_users.avatar/website, canvas_media.caption. This app's actual
     * production data has featured_image URLs (long Unsplash CDN URLs with
     * query strings) exceeding 255 characters, which the vendor's own
     * VARCHAR(255) does not accommodate - confirmed by a production
     * migration failure (SQLSTATE[22001]) on 2026-09-08. The narrower
     * columns were apparently only ever safe under v6 because that data was
     * written before this app's MySQL connection ran in strict mode (or the
     * live column was manually widened outside of any tracked migration,
     * matching the undocumented-schema-change pattern already noted for the
     * `users` table in CLAUDE.md) - either way, matching Canvas's own
     * precedent of using text() for other free-form fields is the safer
     * choice going forward.
     */
    public function up(): void
    {
        Schema::create('canvas_topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug');
            $table->string('name');
            $table->foreignUuid('user_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
            $table->unique(['slug', 'user_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('canvas_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug');
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->text('body')->nullable();
            $table->dateTime('published_at')->nullable()->index();
            $table->text('featured_image')->nullable();
            $table->text('featured_image_caption')->nullable();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->uuid('topic_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->json('pending')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['slug', 'user_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('topic_id')->references('id')->on('canvas_topics')->nullOnDelete();
            $table->fullText('body');
            $table->fullText(['title', 'summary']);
        });

        Schema::create('canvas_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug');
            $table->string('name');
            $table->foreignUuid('user_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
            $table->unique(['slug', 'user_id']);
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('canvas_posts_tags', function (Blueprint $table) {
            $table->uuid('post_id');
            $table->uuid('tag_id');
            $table->unique(['post_id', 'tag_id']);
        });

        Schema::create('canvas_views', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('post_id')->index();
            $table->string('ip')->nullable();
            $table->text('agent')->nullable();
            $table->string('referer')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::create('canvas_visits', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('post_id');
            $table->string('ip')->nullable();
            $table->text('agent')->nullable();
            $table->string('referer')->nullable();
            $table->timestamps();
        });

        Schema::create('canvas_users', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary();
            $table->tinyInteger('role');
            $table->string('username')->nullable()->unique();
            $table->text('summary')->nullable();
            $table->text('avatar')->nullable();
            $table->text('website')->nullable();
            $table->json('social')->nullable();
            $table->string('locale')->nullable();
            $table->string('timezone')->nullable();
            $table->string('theme')->nullable();
            $table->boolean('digest')->default(false);
            $table->json('preferences')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('canvas_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('path');
            $table->string('filename');
            $table->string('original_name')->nullable();
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt')->nullable();
            $table->text('caption')->nullable();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
            $table->index('mime_type');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('canvas_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Drops only the fresh v7 tables this migration created. The v6 data
     * living in canvas_v6_backup_* (created by the previous migration) is
     * never touched here.
     */
    public function down(): void
    {
        Schema::dropIfExists('canvas_posts');
        Schema::dropIfExists('canvas_tags');
        Schema::dropIfExists('canvas_posts_tags');
        Schema::dropIfExists('canvas_views');
        Schema::dropIfExists('canvas_visits');
        Schema::dropIfExists('canvas_users');
        Schema::dropIfExists('canvas_media');
        Schema::dropIfExists('canvas_settings');
        Schema::dropIfExists('canvas_topics');
    }
};
