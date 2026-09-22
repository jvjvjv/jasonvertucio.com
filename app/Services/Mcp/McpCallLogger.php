<?php

namespace App\Services\Mcp;

use App\Models\McpCall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes the record of one MCP request.
 *
 * Kept out of the middleware so the decisions about *what* a record contains
 * live in one testable place, following the `DocumentDownloadLogger` pattern.
 *
 * Logging must never break the endpoint: a failure here is reported to the
 * application log and swallowed.
 */
class McpCallLogger
{
    /**
     * How long a session's reported client identity is remembered, so later
     * calls in that session can be attributed to it.
     */
    private const CLIENT_IDENTITY_TTL_MINUTES = 120;

    public function record(Request $request, ?Response $response, float $startedAtNanos, ?string $forcedOutcome = null, ?int $forcedStatus = null): void
    {
        try {
            $status = $forcedStatus ?? $response?->getStatusCode();
            $sessionId = $this->sessionId($request, $response);
            $identity = $sessionId !== null ? $this->rememberedClientIdentity($sessionId) : [];

            McpCall::create([
                'session_id' => $sessionId,
                'method' => (string) ($request->json('method') ?? 'unknown'),
                'tool_name' => $this->toolName($request),
                'user_id' => $request->user()?->getAuthIdentifier(),
                'client_address' => $request->header('CF-Connecting-IP') ?? $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 512) ?: null,
                'client_name' => $identity['name'] ?? null,
                'client_version' => $identity['version'] ?? null,
                'outcome' => $forcedOutcome ?? $this->outcome($status, $response),
                'status_code' => $status,
                'duration_ms' => (int) round((hrtime(true) - $startedAtNanos) / 1_000_000),
            ]);
        } catch (\Throwable $e) {
            Log::warning('mcp.call-log: failed to record call', ['message' => $e->getMessage()]);
        }
    }

    /**
     * Remember the client identity a session reported at initialization, so
     * that later calls carrying only the session id can be attributed to it.
     */
    public function rememberClientIdentity(string $sessionId, ?string $name, ?string $version): void
    {
        Cache::put(
            $this->identityCacheKey($sessionId),
            ['name' => $name, 'version' => $version],
            now()->addMinutes(self::CLIENT_IDENTITY_TTL_MINUTES),
        );

        // The initialize call's own row is written before the server has
        // handled the request, so backfill it once the identity is known.
        McpCall::query()
            ->where('session_id', $sessionId)
            ->whereNull('client_name')
            ->update(['client_name' => $name, 'client_version' => $version]);
    }

    /**
     * @return array{name?: string|null, version?: string|null}
     */
    private function rememberedClientIdentity(string $sessionId): array
    {
        $identity = Cache::get($this->identityCacheKey($sessionId));

        return is_array($identity) ? $identity : [];
    }

    private function identityCacheKey(string $sessionId): string
    {
        return 'mcp:client-identity:'.sha1($sessionId);
    }

    private function sessionId(Request $request, ?Response $response): ?string
    {
        $fromResponse = $response?->headers->get('MCP-Session-Id');

        return $fromResponse ?: ($request->header('MCP-Session-Id') ?: null);
    }

    private function toolName(Request $request): ?string
    {
        $name = $request->json('params.name');

        return is_string($name) && $name !== '' ? mb_substr($name, 0, 128) : null;
    }

    private function outcome(?int $status, ?Response $response): string
    {
        if ($status === 429) {
            return McpCall::OUTCOME_THROTTLED;
        }

        if ($status === 401) {
            return McpCall::OUTCOME_UNAUTHENTICATED;
        }

        if ($status !== null && $status >= 400) {
            return McpCall::OUTCOME_ERROR;
        }

        return $this->bodyReportsError($response)
            ? McpCall::OUTCOME_ERROR
            : McpCall::OUTCOME_OK;
    }

    /**
     * A JSON-RPC response can carry a protocol error, or a tool result flagged
     * as an error, while the HTTP status stays 200.
     */
    private function bodyReportsError(?Response $response): bool
    {
        if ($response === null || ! $response->headers->contains('Content-Type', 'application/json')) {
            return false;
        }

        $payload = json_decode((string) $response->getContent(), true);

        if (! is_array($payload)) {
            return false;
        }

        return isset($payload['error']) || ($payload['result']['isError'] ?? false) === true;
    }
}
