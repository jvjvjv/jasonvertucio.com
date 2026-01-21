<?php

use Illuminate\Support\Facades\Route;
use BSPDX\AuthKit\Http\Controllers\TwoFactorAuthController;
use BSPDX\AuthKit\Http\Controllers\PasskeyAuthController;
use BSPDX\AuthKit\Http\Controllers\ProfileController;
use BSPDX\AuthKit\Http\Controllers\LoginController;

/*
|--------------------------------------------------------------------------
| AuthKit Web Routes
|--------------------------------------------------------------------------
|
| These are example routes for the BSPDX AuthKit package.
| Copy these routes to your routes/web.php file and customize as needed.
|
| Make sure to add the 'web' middleware group and authentication as needed.
|
*/


// Profile Routes
Route::middleware(config('authkit.profile.middleware', ['web', 'auth']))->group(function () {
    Route::get(config('authkit.profile.path', '/profile'), [ProfileController::class, 'show'])
        ->name('authkit.profile.show');

    Route::put(config('authkit.profile.path', '/profile') . '/auth-preferences', [ProfileController::class, 'updateAuthPreferences'])
        ->name('authkit.profile.auth-preferences.update');
});

// Passwordless Login Routes
Route::middleware(['web', 'guest'])->group(function () {
    Route::post('/login/methods', [LoginController::class, 'getAuthMethods'])
        ->name('authkit.login.methods');

    Route::post('/login/totp', [LoginController::class, 'authenticateWithTotp'])
        ->name('authkit.login.totp');
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
