<?php

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\LoginMethodsController;

// Fortify auto-registers these routes:
// POST /login - Login handler
// POST /logout - Logout handler
// GET/POST /password/reset - Password reset
// POST /two-factor-challenge - 2FA challenge

// Keystone/Fortify authentication routes
Route::get('/login', function () {
    return view('auth.login');
})->name('login')->middleware('guest');

Route::post('/login/check-email', [LoginMethodsController::class, 'check'])
    ->middleware(['web', 'guest'])
    ->name('login.check-email');

Route::get('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout.get');
