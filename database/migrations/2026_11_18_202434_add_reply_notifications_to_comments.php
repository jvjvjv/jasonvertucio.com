<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A reply subscription is active only when `notify_replies` is set and
     * `notify_replies_confirmed_at` is filled. Every existing comment starts
     * unsubscribed.
     */
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->boolean('notify_replies')->default(false)->after('edited_at');
            $table->timestamp('notify_replies_confirmed_at')->nullable()->after('notify_replies');
        });

        Schema::create('comment_email_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_email_opt_outs');

        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn(['notify_replies', 'notify_replies_confirmed_at']);
        });
    }
};
