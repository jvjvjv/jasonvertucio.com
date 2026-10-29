<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The request columns describe the honeypot request that caused a ban;
     * bans raised by a moderator or by spam escalation have no such request.
     */
    public function up(): void
    {
        Schema::table('ip_ban', function (Blueprint $table) {
            $table->id()->first();
            $table->string('banned_method')->nullable()->change();
            $table->string('banned_url')->nullable()->change();
            $table->text('banned_body')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ip_ban', function (Blueprint $table) {
            $table->dropColumn('id');
        });
    }
};
