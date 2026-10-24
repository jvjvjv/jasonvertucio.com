<?php

namespace App\Listeners;

use App\Services\Mcp\McpCallLogger;
use Laravel\Mcp\Events\SessionInitialized;

/**
 * Captures the calling agent's self-reported identity from the MCP
 * initialization handshake.
 *
 * This is the one place a caller says what it is — "claude-desktop 1.4" versus
 * an unnamed script — and it arrives only once per session, so it is stored
 * against the session id and joined onto the session's later calls.
 *
 * A client that reports nothing is recorded as unidentified; that must never
 * fail the handshake.
 */
class RecordMcpClientIdentity
{
    public function __construct(private McpCallLogger $logger) {}

    public function handle(SessionInitialized $event): void
    {
        $this->logger->rememberClientIdentity(
            $event->sessionId,
            $event->clientName(),
            $event->clientVersion(),
        );
    }
}
