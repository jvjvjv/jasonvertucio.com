<?php

namespace App\Http\Middleware;

use Closure;
use Cache;
use App\Models\IpBan;

class IpMiddleware
{

    public function handle($request, Closure $next)
    {
      if (Cache::has('banned_ip_list')) {
        $restricted_ips = Cache::get('banned_ip_list');
      } else {
        $restricted_ips = IpBan::all()->map(function ($item) {
          return $item->ip;
        })->toArray();
        Cache::put('banned_ip_list', $restricted_ips, 300);
      }
      if (in_array($request->ip(), $restricted_ips)) {
          abort(403);
      }

    return $next($request);
    }
}
