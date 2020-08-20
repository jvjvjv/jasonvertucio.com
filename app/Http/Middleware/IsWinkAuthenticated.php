<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Http\Request;
use Closure;

class IsWinkAuthenticated
{
    /**
     * The authentication factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $is_authenticated = $this->auth->guard('wink')->check();
        $request->is_wink_authenticated = $is_authenticated;
        return $next($request);
    }
}
