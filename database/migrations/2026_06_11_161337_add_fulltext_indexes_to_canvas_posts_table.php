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
        Schema::table('canvas_posts', function (Blueprint $table) {
            // Add full-text index on body column for searching content
            if (!Schema::hasIndex('canvas_posts', 'body')) {
                $table->fullText('body');
            }

            // Add composite full-text index on title and summary for combined searches
            if (!Schema::hasIndex('canvas_posts', ['title', 'summary'])) {
                $table->fullText(['title', 'summary']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('canvas_posts', function (Blueprint $table) {
            // Drop full-text index on body
            Schema::dropFullText('canvas_posts', 'body');

            // Drop composite full-text index on title and summary
            Schema::dropFullText('canvas_posts', ['title', 'summary']);
        });
    }
};

