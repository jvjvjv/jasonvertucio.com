<?php

use App\Http\Controllers\WordpressController;

Route::get('/wp-admin/load-styles.php', function () {
    return response()->view('wp-load_styles')->header('Content-Type', 'text/css');
});
Route::get('/wp-login.php', [WordpressController::class, 'index']);
Route::post('/wp-login.php', [WordpressController::class, 'ban']);
Route::redirect('/wp-admin', '/wp-login.php');
