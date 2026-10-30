<?php

use App\Http\Controllers\Auth\BspdxLoginController;
use App\Http\Controllers\Auth\ProfileController;
use App\Services\Auth\BspdxOAuthClient;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| bspdx.com is the only way to sign in. Fortify's own routes are switched off
| (Fortify::ignoreRoutes() in FortifyServiceProvider), so there is no password,
| registration, reset or two-factor endpoint; only its logout is reused below.
|
*/

Route::get('/login', function (BspdxOAuthClient $bspdx) {
    return view('auth.login', ['bspdxAvailable' => $bspdx->isAvailable()]);
})->name('login')->middleware('guest');

// Sign in with bspdx.com (authorization code + PKCE). 404 unless enabled
// and configured.
Route::middleware(['guest', 'throttle:federated-login'])->group(function () {
    Route::get('/auth/bspdx/redirect', [BspdxLoginController::class, 'redirect'])->name('auth.bspdx.redirect');
    Route::get('/auth/bspdx/callback', [BspdxLoginController::class, 'callback'])->name('auth.bspdx.callback');
});

// Ending a session needs a POST with a forgery token. A GET is answered with a
// redirect that leaves the session alone, so an old bookmark does not 404 and
// a cross-site image tag cannot sign anyone out.
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth:web')
    ->name('logout');

Route::get('/logout', fn () => redirect('/'))->name('logout.get');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile/tool-visibility', [ProfileController::class, 'updateToolVisibility'])->name('profile.tool-visibility.update');
});
