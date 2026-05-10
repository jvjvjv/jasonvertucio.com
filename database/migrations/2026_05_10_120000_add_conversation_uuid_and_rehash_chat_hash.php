<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Add a stable UUID column and regenerate chat_hash as MD5(uuid).
     * This replaces the brittle SHA1 composite (id:created_at:feature) with a
     * deterministic, input-stable identifier.
     */
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('public_id');
            $table->index('uuid');
        });

        // Backfill UUIDs for all existing records
        DB::table('ai_conversations')
            ->whereNull('uuid')
            ->chunkById(100, function ($conversations) {
                foreach ($conversations as $conversation) {
                    DB::table('ai_conversations')
                        ->where('id', $conversation->id)
                        ->update(['uuid' => (string) Str::uuid()]);
                }
            });

        // Make UUID non-nullable once backfilled
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });

        // Regenerate chat_hash to MD5(uuid) for all conversations
        DB::table('ai_conversations')
            ->chunkById(100, function ($conversations) {
                foreach ($conversations as $conversation) {
                    DB::table('ai_conversations')
                        ->where('id', $conversation->id)
                        ->update(['chat_hash' => md5($conversation->uuid)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropIndex(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
