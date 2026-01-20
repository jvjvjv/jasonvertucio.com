<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\IpBan;
use Symfony\Component\HttpFoundation\Response;

class IpMiddleware {

    public function handle(Request $request, Closure $next): Response {
        if (env('APP_DEBUG'))
            return $next($request);

        if (Cache::has('banned_ip_list')) {
            $restricted_ips = Cache::get('banned_ip_list');
        } else {
            $restricted_ips = IpBan::all()->map(function ($item) {
                return $item->ip;
            })->toArray();
            Cache::put('banned_ip_list', $restricted_ips, 300);
        }
        $ip = $request->header('CF-Connecting-IP') ?? $request->ip();
        if (time() < strtotime('2026-01-05T00:00:00Z')) {
            Log::debug("IP to check: {$ip} - Source: " . ($request->header('CF-Connecting-IP') ? 'CF-Connecting-IP' : 'request->ip()'));
        }
        if (in_array($ip, $restricted_ips)) {
            Log::debug("{$ip} banned. Sending 403.");
            abort(403);
        }

        return $next($request);
    }
}
