<?php

namespace App\Http\Middleware;

use App\Models\McpCall;
use App\Services\Mcp\McpCallLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every request to the public MCP endpoint.
 *
 * Registered *outside* the throttle and the optional-auth middleware, so that a
 * request rejected by either is still recorded. A rejected call that leaves no
 * trace is the one you most want to see.
 *
 * The throttle rejects by throwing, so the exception path records and re-throws
 * rather than swallowing.
 */
class LogMcpCall
{
    public function __construct(private McpCallLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        try {
            $response = $next($request);
        } catch (ThrottleRequestsException $e) {
            $this->logger->record($request, null, $startedAt, McpCall::OUTCOME_THROTTLED, $e->getStatusCode());

            throw $e;
        } catch (HttpExceptionInterface $e) {
            $this->logger->record($request, null, $startedAt, null, $e->getStatusCode());

            throw $e;
        }

        $this->logger->record($request, $response, $startedAt);

        return $response;
    }
}
