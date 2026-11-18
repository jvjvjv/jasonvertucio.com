<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The originating client address of a request, preferring Cloudflare's
 * `CF-Connecting-IP` header, in the same order as IpMiddleware.
 *
 * Unlike a bare `header('CF-Connecting-IP') ?? ip()`, a header that is
 * present but empty or not an IP address is ignored rather than used, so it
 * cannot put a client into a shared or made-up bucket.
 */
class ClientAddress
{
    /**
     * Resolve the client address for a request. A valid forwarded address is
     * returned in its canonical form, so `2001:0DB8::1` and `2001:db8::1`
     * are the same client.
     */
    public static function from(Request $request): ?string
    {
        $forwarded = trim((string) $request->header('CF-Connecting-IP', ''));

        if ($forwarded !== '' && filter_var($forwarded, FILTER_VALIDATE_IP) !== false) {
            return inet_ntop(inet_pton($forwarded));
        }

        return $request->ip();
    }
}
