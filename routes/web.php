<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\FacebookCallbackController;
use App\Http\Controllers\WordpressController;
// use App\Http\Controllers\Auth\LoginController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// AuthKit/Fortify authentication routes
Route::get('/login', function () {
    return view('auth.login');
})->name('login')->middleware('guest');

Route::get('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout');

// Fortify auto-registers these routes:
// POST /login - Login handler
// POST /logout - Logout handler
// GET/POST /password/reset - Password reset
// POST /two-factor-challenge - 2FA challenge

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about/{any?}', function () {
    return redirect('/');
});

Route::view('paper', 'paper')->name('paper');

Route::group(['prefix' => 'blog'], function ($route) {
    $route->get('/', [BlogController::class, 'index'])->name('blog');

    $route->get('/topics', [BlogController::class, 'topics'])->name('topics');
    $route->get('/tags', [BlogController::class, 'tags'])->name('tags');

    $route->get('/topics/{slug}', [BlogController::class, 'topicList'])->name('topicList');
    $route->get('/tags/{slug}', [BlogController::class, 'tagList'])->name('tagList');

    $route->get("/{slug}", [BlogController::class, 'post'])->name('post');
});

Route::any('/mlopnadjs22tn', [FacebookCallbackController::class, 'index']);

// Honeypots
Route::get('/wp-admin/load-styles.php', function () {
    return response()->view('wp-load_styles')->header('Content-Type', 'text/css');
});
Route::get('/wp-login.php', [WordpressController::class, 'index']);
Route::post('/wp-login.php', [WordpressController::class, 'ban']);
Route::redirect('/wp-admin', '/wp-login.php');
