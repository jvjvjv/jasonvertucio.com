<?php

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

// Ending a session needs a POST with a forgery token (Fortify's POST /logout).
// A GET is answered with a redirect that leaves the session alone, so an old
// bookmark does not 404 and a cross-site image tag cannot sign anyone out.
Route::get('/logout', fn () => redirect('/'))->name('logout.get');
