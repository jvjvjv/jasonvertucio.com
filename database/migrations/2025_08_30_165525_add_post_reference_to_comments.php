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
            if (!Schema::hasColumn('comments', 'post_id')) {
                $table->unsignedBigInteger('post_id')->nullable()->after('id');
            }
            // Reference Canvas posts table - only add foreign key if it doesn't exist
            if (!Schema::hasColumn('comments', 'post_id')) {
                $table->foreign('post_id')->references('id')->on('canvas_posts')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['post_id']);
            $table->dropColumn('post_id');
        });
    }
};
