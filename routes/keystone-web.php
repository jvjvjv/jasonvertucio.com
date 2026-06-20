<?php

use BSPDX\Keystone\Http\Controllers\LoginController;
use BSPDX\Keystone\Http\Controllers\PasskeyAuthController;
use BSPDX\Keystone\Http\Controllers\ProfileController;
use BSPDX\Keystone\Http\Controllers\TwoFactorAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Keystone Web Routes
|--------------------------------------------------------------------------
|
| These are example routes for the BSPDX Keystone package.
| Copy these routes to your routes/web.php file and customize as needed.
|
| Make sure to add the 'web' middleware group and authentication as needed.
|
*/

// Profile Routes
Route::middleware(config('keystone.profile.middleware', ['web', 'auth']))->group(function () {
    Route::get(config('keystone.profile.path', '/profile'), [ProfileController::class, 'show'])
        ->name('keystone.profile.show');

    Route::put(config('keystone.profile.path', '/profile').'/auth-preferences', [ProfileController::class, 'updateAuthPreferences'])
        ->name('keystone.profile.auth-preferences.update');
});

// Passwordless Login Routes
Route::middleware(['web', 'guest'])->group(function () {
    Route::post('/login/methods', [LoginController::class, 'getAuthMethods'])
        ->name('keystone.login.methods');

    Route::post('/login/totp', [LoginController::class, 'authenticateWithTotp'])
        ->name('keystone.login.totp');
});

// Two-Factor Authentication Routes
Route::middleware(['web', 'auth'])->group(function () {
    // Enable 2FA
    Route::get('/user/two-factor-authentication', [TwoFactorAuthController::class, 'create'])
        ->name('two-factor.enable');

    Route::post('/user/two-factor-authentication', [TwoFactorAuthController::class, 'store'])
        ->name('two-factor.store');

    // Confirm 2FA
    Route::post('/user/confirmed-two-factor-authentication', [TwoFactorAuthController::class, 'confirm'])
        ->name('two-factor.confirm');

    // Disable 2FA
    Route::delete('/user/two-factor-authentication', [TwoFactorAuthController::class, 'destroy'])
        ->name('two-factor.destroy');

    // Recovery codes
    Route::get('/user/two-factor-recovery-codes', [TwoFactorAuthController::class, 'recoveryCodes'])
        ->name('two-factor.recovery-codes');

    Route::post('/user/two-factor-recovery-codes', [TwoFactorAuthController::class, 'regenerateRecoveryCodes'])
        ->name('two-factor.recovery-codes.regenerate');
});

// Passkey Routes
Route::middleware(['web'])->group(function () {
    // Passkey login (guest)
    Route::get('/passkey/login', [PasskeyAuthController::class, 'loginView'])
        ->name('passkeys.login')
        ->middleware('guest');

    Route::post('/passkey/login/options', [PasskeyAuthController::class, 'loginOptions'])
        ->name('passkeys.login.options')
        ->middleware('guest');

    Route::post('/passkey/authenticate', [PasskeyAuthController::class, 'authenticate'])
        ->name('passkeys.authenticate')
        ->middleware('guest');

    // Passkey management (authenticated)
    Route::middleware(['auth'])->group(function () {
        Route::get('/user/passkeys', [PasskeyAuthController::class, 'registerView'])
            ->name('passkeys.register.view');

        Route::post('/user/passkeys/options', [PasskeyAuthController::class, 'registerOptions'])
            ->name('passkeys.register.options');

        Route::post('/user/passkeys', [PasskeyAuthController::class, 'store'])
            ->name('passkeys.register');

        Route::delete('/user/passkeys/{passkey}', [PasskeyAuthController::class, 'destroy'])
            ->name('passkeys.destroy');
    });
});
