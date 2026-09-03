<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `2025_08_30_165525_add_post_reference_to_comments.php` guards its whole body
     * with `if (! Schema::hasColumn('comments', 'post_id'))`. Where a `post_id`
     * column already existed as a bigint, that guard skipped the migration
     * entirely, so the column was never retyped and the foreign key was never
     * added. Canvas post ids are UUIDs, so inserting a comment against a bigint
     * column truncates the id and the insert fails.
     *
     * The comments table is empty in every environment, so retyping is lossless.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('comments', 'post_id')) {
            Schema::table('comments', function (Blueprint $table) {
                $table->char('post_id', 36)->nullable()->after('id');
            });
        }

        if ($this->postIdType() !== 'char(36)') {
            $this->dropPostIdForeignKey();

            Schema::table('comments', function (Blueprint $table) {
                $table->char('post_id', 36)->nullable()->change();
            });
        }

        if (! $this->hasPostIdForeignKey()) {
            Schema::table('comments', function (Blueprint $table) {
                $table->foreign('post_id')->references('id')->on('canvas_posts')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * Deliberately a no-op: the previous state was a column type that could not
     * hold the ids it referenced.
     */
    public function down(): void
    {
        //
    }

    /**
     * Get the declared column type of `comments.post_id`.
     */
    protected function postIdType(): ?string
    {
        $column = collect(DB::select('describe comments'))
            ->firstWhere('Field', 'post_id');

        return $column?->Type;
    }

    /**
     * Determine whether `comments.post_id` already carries a foreign key.
     */
    protected function hasPostIdForeignKey(): bool
    {
        return $this->postIdForeignKeyName() !== null;
    }

    /**
     * Get the name of the foreign key on `comments.post_id`, if any.
     */
    protected function postIdForeignKeyName(): ?string
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

    /**
     * Drop the foreign key on `comments.post_id` if one is present.
     */
    protected function dropPostIdForeignKey(): void
    {
        $name = $this->postIdForeignKeyName();

        if ($name === null) {
            return;
        }

        Schema::table('comments', function (Blueprint $table) use ($name) {
            $table->dropForeign($name);
        });
    }
};
