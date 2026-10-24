<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves an optional bearer token for the public MCP endpoint.
 *
 * `auth:sanctum` cannot be used here because it rejects unauthenticated
 * requests, which is the opposite of what this endpoint wants: anonymous
 * callers are first-class and receive the public projection.
 *
 * A caller presenting no credential passes through untouched. The route runs
 * outside the `web` middleware group, so no session exists and the default
 * guard resolves no user — CodeTalker's `ToolContext` binding then yields an
 * anonymous context by construction.
 *
 * A caller presenting a credential that does not resolve is rejected rather
 * than quietly downgraded. Someone holding a revoked token who keeps receiving
 * 200s full of blanks has no way to tell a permission boundary from an expired
 * credential.
 */
class AuthenticateMcpCaller
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasHeader('Authorization')) {
            return $next($request);
        }

        $user = Auth::guard('sanctum')->user();

        if ($user === null) {
            return new JsonResponse([
                'error' => 'invalid_token',
                'message' => 'The presented bearer token is not valid. Omit the Authorization header entirely to call this endpoint anonymously.',
            ], 401);
        }

        Auth::shouldUse('sanctum');

        return $next($request);
    }
}
