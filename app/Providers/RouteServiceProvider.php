<?php

namespace App\Providers;

use App\Models\AiChatBot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        // Register hash-based access routes for each active chat bot.
        // These allow accessing a specific conversation from any computer.
        // Format: /chat/{bot-slug}/{hash}
        $activeBots = AiChatBot::active()->get(['id', 'slug']);
        foreach ($activeBots as $bot) {
            $prefix = 'chat/' . $bot->slug;
            Route::middleware(['web', \App\Http\Middleware\HandleChatInertiaRequests::class])
                ->prefix($prefix)
                ->name("chat-bot-{$bot->slug}.")
                ->group(function () use ($bot) {
                    // Exclude reserved words that conflict with other routes: new, reset, switch, messages
                    Route::get('/{hash}', function (string $hash, Request $request) {
                        // Validate hash format: must be 32 hex characters and not a reserved word
                        if (!preg_match('/^[a-f0-9]{32}$/', $hash)) {
                            abort(404);
                        }
                        
                        return resolve(\App\Http\Controllers\ChatBotController::class)->showByHash($request, $hash);
                    })->name('by-hash');
                });
        }
    }
}
