<?php

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

// Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
// Route::post('login', [LoginController::class, 'login']);
// Route::post('logout', [LoginController::class, 'logout'])->name('logout');
// Route::get('logout', [LoginController::class, 'logout'])->name('get_logout');

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
