<?php

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

Route::get('/', function () {
    return view('splash');
});

Route::view('paper', 'paper')->name('paper');
Route::get('/wp-login.php', 'WordpressController@index');
Route::post('/wp-login.php', 'WordpressController@ban');
Route::redirect('/wp-admin','/wp-login.php');

Route::namespace('Wink\Http\Controllers')
  ->prefix('api/v1')
  ->group(function () {
    Route::get('posts', '\Wink\Http\Controllers\PostsController@index');
  });
