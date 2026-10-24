<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canvas v6 -> v7 upgrade, step 1 of 3 (see openspec/changes/migrate-canvas-v6-to-v7).
     *
     * Renames every v6 canvas_* table out of the way so the next migration can
     * create fresh v7-shaped tables under the original names. Nothing is
     * dropped or deleted here - every row in every table survives under its
     * new canvas_v6_backup_* name. MySQL/InnoDB automatically repoints the
     * live `comments.post_id` foreign key at the renamed table.
     */
    public function up(): void
    {
        foreach ($this->tables() as $from => $to) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }
    }

    /**
     * Reverses the rename. The backup tables return to their original names;
     * this only works cleanly if the v7 tables created by the next migration
     * have already been rolled back first.
     */
    public function down(): void
    {
        foreach (array_flip($this->tables()) as $from => $to) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }
    }

    /**
     * @return array<string, string> v6 table name => backup table name
     */
    private function tables(): array
    {
        return [
            'canvas_posts' => 'canvas_v6_backup_posts',
            'canvas_posts_tags' => 'canvas_v6_backup_posts_tags',
            'canvas_posts_topics' => 'canvas_v6_backup_posts_topics',
            'canvas_tags' => 'canvas_v6_backup_tags',
            'canvas_topics' => 'canvas_v6_backup_topics',
            'canvas_users' => 'canvas_v6_backup_users',
            'canvas_views' => 'canvas_v6_backup_views',
            'canvas_visits' => 'canvas_v6_backup_visits',
        ];
    }
};
