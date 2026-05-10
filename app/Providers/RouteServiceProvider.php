<?php

namespace App\Providers;

use App\Models\AiChatBot;
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
    public const HOME = '/canvas';

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
            Route::middleware([\App\Http\Middleware\HandleChatInertiaRequests::class])
                ->prefix($prefix)
                ->name("chat-bot-{$bot->slug}.")
                ->group(function () use ($bot) {
                    Route::get('/{hash}', [\App\Http\Controllers\ChatBotController::class, 'showByHash'])
                        ->name('by-hash');
                });
        }
    }
}
