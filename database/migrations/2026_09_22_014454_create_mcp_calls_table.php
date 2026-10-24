<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per request to the public MCP endpoint.
 *
 * The endpoint is public, so this table grows with untrusted traffic and is
 * swept on a schedule (see `mcp-server.log_retention_days`).
 *
 * `client_name` and `client_version` are the calling agent's self-report from
 * the initialization handshake. They are recorded against the session rather
 * than the call, so later rows in a session are attributed by joining on
 * `session_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_calls', function (Blueprint $table): void {
            $table->id();

            $table->string('session_id', 191)->nullable()->index();
            $table->string('method', 64)->index();
            $table->string('tool_name', 128)->nullable();

            // Null means anonymous, which is the common case by design.
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('client_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();

            $table->string('client_name', 128)->nullable();
            $table->string('client_version', 64)->nullable();

            $table->string('outcome', 32)->index();
            $table->unsignedInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_calls');
    }
};
