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
Route::get('login', 'Auth\LoginController@showLoginForm')->name('login');
Route::post('login', 'Auth\LoginController@login');
Route::post('logout', 'Auth\LoginController@logout')->name('logout');
Route::get('logout', 'Auth\LoginController@logout')->name('get_logout');

Route::get('/', 'HomeController@index')->name('home');
Route::get('/about/{any?}', function() {
  return redirect('/');
});

Route::view('paper', 'paper')->name('paper');
Route::group(['prefix'=>'blog'], function($route) {
  $route->get('/', 'BlogController@index')->name('blog');
  $route->get("/{slug}", 'BlogController@post')->name('post');
});


// Honeypots
Route::get('/wp-admin/load-styles.php',function() {
  return response()->view('wp-load_styles')->header('Content-Type','text/css');
});
Route::get('/wp-login.php', 'WordpressController@index');
Route::post('/wp-login.php', 'WordpressController@ban');
Route::redirect('/wp-admin','/wp-login.php');
