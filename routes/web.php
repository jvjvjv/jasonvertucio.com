<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\FacebookCallbackController;

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

Route::get('/', [HomeController::class, 'index'])->name('home');

// Legacy redirects
Route::get('/about/{any?}', function () {
    return redirect('/');
});

// Facebook callback route -- I don't think I use this anymore
Route::any('/mlopnadjs22tn', [FacebookCallbackController::class, 'index']);


require base_path('routes/auth.php');
require base_path('routes/admin.php');
require base_path('routes/admin-ai.php');
require base_path('routes/admin-resume.php');
require base_path('routes/resume.php');
require base_path('routes/blog.php');
require base_path('routes/honeypots.php');

require base_path('routes/api-web.php');
