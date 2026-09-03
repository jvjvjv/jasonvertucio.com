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
            $table->timestamp('approved_at')->nullable()->after('message');
            $table->boolean('is_spam')->default(false)->after('approved_at');
            $table->unsignedTinyInteger('depth')->default(0)->after('is_spam');
            $table->string('ip_address', 45)->nullable()->after('depth');
            $table->text('user_agent')->nullable()->after('ip_address');

            $table->index(['post_id', 'approved_at', 'is_spam'], 'comments_thread_visibility_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_thread_visibility_index');
            $table->dropColumn(['approved_at', 'is_spam', 'depth', 'ip_address', 'user_agent']);
        });
    }
};
