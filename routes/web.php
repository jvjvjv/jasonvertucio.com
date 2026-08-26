<?php

use App\Http\Controllers\FacebookCallbackController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

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
require base_path('routes/passkeys.php');
require base_path('routes/admin.php');
require base_path('routes/admin-resume.php');
require base_path('routes/resume.php');
require base_path('routes/blog.php');
require base_path('routes/honeypots.php');
require base_path('routes/codetalker-admin.php');

require base_path('routes/api-web.php');

// code-talker 0.11.0 no longer registers routes itself (see
// routes/codetalker-chatbots.php's own header comment). Must load last: its
// root-level `/{aiChatBot:slug}` wildcard would otherwise swallow every
// literal route registered above.
require base_path('routes/codetalker-chatbots.php');
