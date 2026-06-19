<?php

namespace App\Http\Middleware;

use App\Models\AiChatBot;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckChatBotAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $bot = $request->route('aiChatBot');

        if (! ($bot instanceof AiChatBot)) {
            return $next($request);
        }

        $allowedRoles = $bot->allowed_roles ?? [];

        if ($allowedRoles === []) {
            return $next($request);
        }

        $user = $request->user();

        abort_unless($user instanceof User && $user->hasAnyRole($allowedRoles), 403);

        return $next($request);
    }
}
