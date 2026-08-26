<?php

use App\Http\Controllers\ChatBotController;
use App\Http\Middleware\CheckChatBotAccess;
use App\Http\Middleware\HandleChatInertiaRequests;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Chat Bot Routes
|--------------------------------------------------------------------------
|
| code-talker 0.11.0 no longer registers routes itself (see the package's
| CHANGELOG, "All routes are removed"). This app owns route dispatch fully;
| it's required directly from routes/web.php, last, so the root-level wildcard
| below does not swallow more-specific literal routes registered above it.
|
*/

Route::middleware(['web', HandleChatInertiaRequests::class])
    ->get('/chats', [ChatBotController::class, 'index'])
    ->name('chat-bots.index');

Route::middleware(['web', HandleChatInertiaRequests::class])
    ->get('/chats/statuses', [ChatBotController::class, 'statuses'])
    ->name('chat-bots.statuses');

// Hash/UUID-based conversation links
Route::middleware(['web', HandleChatInertiaRequests::class])
    ->prefix('chat')
    ->group(function () {
        Route::get('/{slug}/{hash}', [ChatBotController::class, 'showByHash'])
            ->where('hash', '[a-f0-9]{32}')
            ->name('chat-bot.by-hash');
    });

// Shared route group for per-bot endpoints — access control enforced by middleware.
$chatBotRoutes = function () {
    Route::get('/{aiChatBot:slug}', [ChatBotController::class, 'show'])->name('show');
    Route::get('/{aiChatBot:slug}/new', [ChatBotController::class, 'newChat'])->name('new');
    Route::get('/{aiChatBot:slug}/status', [ChatBotController::class, 'status'])->name('status');
    Route::post('/{aiChatBot:slug}/warmup', [ChatBotController::class, 'warmup'])->name('warmup');
    Route::post('/{aiChatBot:slug}/messages', [ChatBotController::class, 'message'])->name('message');
    Route::post('/{aiChatBot:slug}/reset', [ChatBotController::class, 'reset'])->name('reset');
    Route::post('/{aiChatBot:slug}/switch', [ChatBotController::class, 'switch'])->name('switch');
};

// /chat/{slug} prefixed routes
Route::middleware(['web', HandleChatInertiaRequests::class, CheckChatBotAccess::class])
    ->prefix('chat')
    ->name('chat-bots.chat.')
    ->group($chatBotRoutes);

// Root-level /{slug} routes — must be last so the wildcard does not match
// literal paths registered before this file is loaded.
Route::middleware(['web', HandleChatInertiaRequests::class, CheckChatBotAccess::class])
    ->name('chat-bots.root.')
    ->group($chatBotRoutes);
