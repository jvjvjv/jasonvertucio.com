<?php

use App\Http\Middleware\AuthenticateMcpCaller;
use App\Http\Middleware\LogMcpCall;
use App\Mcp\Servers\PublicServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Routes
|--------------------------------------------------------------------------
|
| laravel/mcp loads this file itself (McpServiceProvider::registerRoutes) via
| a bare Route::group with no middleware group — no session, no CSRF. Globally
| appended middleware such as IpMiddleware still applies.
|
| Mcp::web() also registers GET and DELETE on the same path returning 405, as
| the transport spec requires; only POST carries JSON-RPC.
|
*/

Mcp::web('/mcp', PublicServer::class)
    ->middleware([
        // Outermost, so a request rejected by the throttle or by the bearer
        // check below is still recorded.
        LogMcpCall::class,
        AuthenticateMcpCaller::class,
        'throttle:mcp',
    ]);
