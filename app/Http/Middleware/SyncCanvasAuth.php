<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SyncCanvasAuth
{
    /**
     * Sync web authentication to canvas guard.
     *
     * If a user is logged in via the web guard but not the canvas guard,
     * set the canvas guard to use the same user for this request.
     * We use setUser() instead of login() to avoid session conflicts.
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('web')->check() && ! Auth::guard('canvas')->check()) {
            Auth::guard('canvas')->setUser(Auth::guard('web')->user());
        }

        return $next($request);
    }
}
