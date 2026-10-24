<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyJellyfinWebhook
{
    /**
     * Refuse any request that does not carry the configured shared secret.
     * With no secret configured every request is refused.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.jellyfin.webhook_secret');
        $supplied = (string) $request->header('X-Webhook-Secret', '');

        if (! is_string($expected) || $expected === '') {
            $this->logRefusal('no secret configured');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        if (! hash_equals($expected, $supplied)) {
            $this->logRefusal($supplied === '' ? 'secret missing' : 'secret mismatch');

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }

    /**
     * Logged at most once a minute so a flood of refusals cannot fill the log
     * but a misconfigured Jellyfin server is still diagnosable.
     */
    private function logRefusal(string $reason): void
    {
        if (Cache::add('jellyfin-webhook-refusal-logged', true, 60)) {
            Log::warning('Jellyfin webhook refused', ['reason' => $reason]);
        }
    }
}
