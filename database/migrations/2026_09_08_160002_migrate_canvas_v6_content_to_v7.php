<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A real image URL is never remotely this long (browsers themselves cap
     * URLs around ~2000 chars). Anything past this is almost certainly bad
     * data - e.g. a base64 image blob pasted into the URL field by mistake -
     * not a legitimate value that a wider column should preserve.
     */
    private const MAX_FEATURED_IMAGE_LENGTH = 2048;

    /**
     * Canvas v6 -> v7 upgrade, step 3 of 3 (see openspec/changes/migrate-canvas-v6-to-v7).
     *
     * Repopulates the fresh v7 tables (created by the previous migration)
     * from the canvas_v6_backup_* tables (created by the rename migration).
     * Only ever inserts into the new tables and never deletes or modifies a
     * canvas_v6_backup_* row - they are left exactly as the rename left them.
     *
     * Findings from inspecting this app's actual data before writing this:
     * - canvas_posts/tags/topics.user_id already equal a valid host
     *   users.id directly (single-author blog) - no remapping needed there.
     * - canvas_users (the access/role table) is different: its own `id` is
     *   a separate identity space from users.id, so it is matched to a host
     *   user by email instead.
     * - canvas_posts_topics carries no ordering column, so when a post has
     *   more than one topic the lowest topic_id wins (deterministic
     *   tie-break) and it is logged. As of writing, zero posts in this
     *   app's data have more than one topic, so this path is untested by
     *   real data and exists for robustness only.
     *
     * The copy* calls are wrapped in a single DB::transaction(): a first
     * production run hit a bad-data failure partway through (SQLSTATE[22001]
     * on a too-narrow column, fixed in the previous migration) with no
     * transaction in place, which left whatever had already been inserted
     * sitting in the database. A retry then hit duplicate-key errors trying
     * to re-insert those same rows instead of failing cleanly or resuming.
     * Wrapping the inserts in a transaction means any future failure rolls
     * everything back to empty automatically, so a fix-and-retry is always
     * safe with no manual cleanup required first.
     *
     * A featured_image value over MAX_FEATURED_IMAGE_LENGTH is nulled out
     * rather than stored (and logged) - the previous migration widened the
     * column to text() for legitimately long URLs (confirmed working on a
     * ~440-char Unsplash URL), but a value this far past any real URL
     * length is data quality noise (an accidental paste), not content worth
     * preserving in a URL field.
     */
    public function up(): void
    {
        $this->dropCommentsForeignKeyIfExists();

        DB::transaction(function (): void {
            $this->copyTopics();
            $this->copyTags();
            $this->copyPostsWithResolvedTopic();
            $this->copyPostsTags();
            $this->copyViewsAndVisits();
            $this->copyCanvasUsers();
        });

        $this->addCommentsForeignKey();
    }

    /**
     * Empties the tables this migration populated and repoints the comments
     * foreign key back at the backup table, restoring the state left by the
     * previous two migrations. The canvas_v6_backup_* tables are untouched.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            if ($this->commentsForeignKeyName() !== null) {
                $table->dropForeign(['post_id']);
            }
        });

        DB::table('canvas_users')->delete();
        DB::table('canvas_posts_tags')->delete();
        DB::table('canvas_views')->delete();
        DB::table('canvas_visits')->delete();
        DB::table('canvas_posts')->delete();
        DB::table('canvas_tags')->delete();
        DB::table('canvas_topics')->delete();

        if (Schema::hasTable('canvas_v6_backup_posts')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->foreign('post_id')->references('id')->on('canvas_v6_backup_posts')->nullOnDelete();
            });
        }
    }

    private function copyTopics(): void
    {
        foreach (DB::table('canvas_v6_backup_topics')->get() as $topic) {
            DB::table('canvas_topics')->insert((array) $topic);
        }
    }

    private function copyTags(): void
    {
        foreach (DB::table('canvas_v6_backup_tags')->get() as $tag) {
            DB::table('canvas_tags')->insert((array) $tag);
        }
    }

    private function copyPostsWithResolvedTopic(): void
    {
        $topicsByPost = DB::table('canvas_v6_backup_posts_topics')
            ->orderBy('topic_id')
            ->get()
            ->groupBy('post_id');

        foreach (DB::table('canvas_v6_backup_posts')->get() as $post) {
            $row = (array) $post;
            $postTopics = $topicsByPost->get($post->id);

            $row['topic_id'] = null;

            if ($postTopics && $postTopics->isNotEmpty()) {
                $row['topic_id'] = $postTopics->first()->topic_id;

                if ($postTopics->count() > 1) {
                    Log::info('[canvas-v6-to-v7] post had multiple topics; collapsed to lowest topic_id', [
                        'post_id' => $post->id,
                        'slug' => $post->slug,
                        'topic_ids' => $postTopics->pluck('topic_id')->all(),
                        'chosen_topic_id' => $row['topic_id'],
                    ]);
                }
            }

            $row['pending'] = null;

            if (is_string($row['featured_image']) && strlen($row['featured_image']) > self::MAX_FEATURED_IMAGE_LENGTH) {
                Log::info('[canvas-v6-to-v7] featured_image far exceeds any real URL length; nulled out rather than stored', [
                    'post_id' => $post->id,
                    'slug' => $post->slug,
                    'length' => strlen($row['featured_image']),
                ]);

                $row['featured_image'] = null;
            }

            DB::table('canvas_posts')->insert($row);
        }
    }

    private function copyPostsTags(): void
    {
        foreach (DB::table('canvas_v6_backup_posts_tags')->get() as $row) {
            DB::table('canvas_posts_tags')->insert((array) $row);
        }
    }

    private function copyViewsAndVisits(): void
    {
        DB::statement('INSERT INTO canvas_views (id, post_id, ip, agent, referer, created_at, updated_at)
            SELECT id, post_id, ip, agent, referer, created_at, updated_at FROM canvas_v6_backup_views');

        DB::statement('INSERT INTO canvas_visits (id, post_id, ip, agent, referer, created_at, updated_at)
            SELECT id, post_id, ip, agent, referer, created_at, updated_at FROM canvas_v6_backup_visits');
    }

    private function copyCanvasUsers(): void
    {
        $backupUsers = DB::table('canvas_v6_backup_users')->get();

        foreach ($backupUsers as $backupUser) {
            if ($backupUser->role === null) {
                Log::info('[canvas-v6-to-v7] skipped canvas_users backup row with no role (no access under v7)', [
                    'email' => $backupUser->email,
                ]);

                continue;
            }

            $hostUser = DB::table('users')->where('email', $backupUser->email)->first();

            if ($hostUser === null) {
                Log::info('[canvas-v6-to-v7] skipped canvas_users backup row with no matching host user', [
                    'email' => $backupUser->email,
                    'role' => $backupUser->role,
                ]);

                continue;
            }

            DB::table('canvas_users')->insert([
                'user_id' => $hostUser->id,
                'role' => $backupUser->role,
                'username' => $backupUser->username,
                'summary' => $backupUser->summary,
                'avatar' => $backupUser->avatar,
                'website' => null,
                'social' => null,
                'locale' => $backupUser->locale,
                'timezone' => null,
                'theme' => null,
                'digest' => (bool) $backupUser->digest,
                'preferences' => null,
                'created_at' => $backupUser->created_at,
                'updated_at' => $backupUser->updated_at,
            ]);
        }
    }

    private function dropCommentsForeignKeyIfExists(): void
    {
        if ($this->commentsForeignKeyName() === null) {
            return;
        }

        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
        });
    }

    private function addCommentsForeignKey(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreign('post_id')->references('id')->on('canvas_posts')->nullOnDelete();
        });
    }

    private function commentsForeignKeyName(): ?string
    {
        $constraint = DB::selectOne(
            'select CONSTRAINT_NAME as name from information_schema.KEY_COLUMN_USAGE
             where TABLE_SCHEMA = database()
               and TABLE_NAME = ?
               and COLUMN_NAME = ?
               and REFERENCED_TABLE_NAME is not null',
            ['comments', 'post_id']
        );

        return $constraint?->name;
    }
};
