<?php

use App\Http\Middleware\IpMiddleware;
use App\Http\Middleware\PreventFraming;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The customizations below used to live in app/Http/Kernel.php, which
        // this Laravel 12 bootstrap style never actually loads (confirmed via
        // app(Illuminate\Contracts\Http\Kernel::class) resolving to the
        // framework's own Illuminate\Foundation\Http\Kernel, not App\Http\Kernel)
        // — so none of it was running. Restored here, which is where Laravel
        // 11+ actually expects this configuration.

        // local-proxy (this machine's TLS-terminating reverse proxy) and any
        // production load balancer send X-Forwarded-*; trust them so
        // asset()/url() generate the request's real scheme/host instead of
        // always assuming plain HTTP.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        // WordPress-honeypot IP ban list — global (not just the 'web' group)
        // so it also covers API-namespaced requests, matching the original
        // Kernel.php placement.
        $middleware->append(IpMiddleware::class);

        $middleware->web(append: [
            PreventFraming::class,
        ]);

        // Facebook's comment webhook posts here with no CSRF token.
        $middleware->validateCsrfTokens(except: [
            '/mlopnadjs22tn',
        ]);

        // Framework defaults: an unauthenticated non-JSON request redirects
        // nowhere (no path configured), and an already-authenticated guest
        // route redirects to a Laravel-standard path. This app wants /login
        // and / respectively — the callback only runs for non-JSON requests,
        // Authenticate::unauthenticated() already short-circuits JSON ones.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
