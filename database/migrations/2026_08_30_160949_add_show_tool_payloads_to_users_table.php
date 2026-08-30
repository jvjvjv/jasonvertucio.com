<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('show_tool_payloads')
                ->default(false)
                ->after('require_password')
                ->comment('Whether this user sees tool call arguments/results in chat (requires manage-ai-tools permission too)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('show_tool_payloads');
        });
    }
};
