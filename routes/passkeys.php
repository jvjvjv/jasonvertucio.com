<?php

use App\Http\Controllers\Auth\PasskeyController;
use App\Http\Controllers\Auth\PasswordlessLoginController;
use App\Http\Controllers\Auth\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Profile Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])
        ->name('profile.show');

    Route::put('/profile/auth-preferences', [ProfileController::class, 'updateAuthPreferences'])
        ->name('profile.auth-preferences.update');

    Route::put('/profile/tool-visibility', [ProfileController::class, 'updateToolVisibility'])
        ->name('profile.tool-visibility.update');
});

/*
|--------------------------------------------------------------------------
| Passwordless TOTP Login
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'guest'])->group(function () {
    Route::post('/login/totp', [PasswordlessLoginController::class, 'authenticateWithTotp'])
        ->name('login.totp');
});

/*
|--------------------------------------------------------------------------
| Passkey Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['web', 'guest'])->group(function () {
    Route::post('/passkey/login/options', [PasskeyController::class, 'loginOptions'])
        ->name('passkeys.login.options');

    Route::post('/passkey/authenticate', [PasskeyController::class, 'authenticate'])
        ->name('passkeys.authenticate');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/user/passkeys', [PasskeyController::class, 'index'])
        ->name('passkeys.index');

    Route::post('/user/passkeys/options', [PasskeyController::class, 'registerOptions'])
        ->name('passkeys.register.options');

    Route::post('/user/passkeys', [PasskeyController::class, 'store'])
        ->name('passkeys.register');

    Route::delete('/user/passkeys/{passkeyId}', [PasskeyController::class, 'destroy'])
        ->name('passkeys.destroy');
});
