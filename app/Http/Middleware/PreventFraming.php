<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventFraming
{
    /**
     * Block embedding of application pages in third-party iframes.
     *
     * Same-origin framing is left intact (SAMEORIGIN / frame-ancestors 'self')
     * since the admin mail-preview page embeds its own preview endpoint in an iframe.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");

        return $response;
    }
}
