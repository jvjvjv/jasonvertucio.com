<?php

use App\Http\Controllers\LocalMediaController;
use App\Http\Middleware\VerifyJellyfinWebhook;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Jellyfin Webhook and Currently Watching API
// These will be accessible at /api/event/@2028 and /api/currently-watching
Route::post('/event/@2028', [LocalMediaController::class, 'index'])
    ->middleware(['throttle:media-webhook', VerifyJellyfinWebhook::class]);
Route::middleware('throttle:media-read')->group(function () {
    Route::get('/currently-watching', [LocalMediaController::class, 'currentlyWatching']);
    Route::get('/media-stats', [LocalMediaController::class, 'mediaStats']);
});
