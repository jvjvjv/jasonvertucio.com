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

// Route::get('login', 'Auth\LoginController@showLoginForm')->name('login');
// Route::post('login', 'Auth\LoginController@login');
// Route::post('logout', 'Auth\LoginController@logout')->name('logout');
// Route::get('logout', 'Auth\LoginController@logout')->name('get_logout');

Route::get('/', 'HomeController@index')->name('home');
Route::get('/about/{any?}', function() {
  return redirect('/');
});

Route::view('paper', 'paper')->name('paper');

Route::group(['prefix'=>'blog'], function($route) {
  $route->get('/', 'BlogController@index')->name('blog');
  $route->get('/topics/{slug}', 'BlogController@topicList')->name('topicList');
  $route->get('/tags/{slug}', 'BlogController@tagList')->name('tagList');
  $route->get("/{slug}", 'BlogController@post')->name('post');
});

/** Remove Canvas UI 
Route::prefix('canvas-ui')->group(function () {
  Route::prefix('api')->group(function () {
  Route::get('posts', [\App\Http\Controllers\CanvasUiController::class, 'getPosts']);
  Route::get('posts/{slug}', [\App\Http\Controllers\CanvasUiController::class, 'showPost'])
    ->middleware('Canvas\Http\Middleware\Session');

  Route::get('tags', [\App\Http\Controllers\CanvasUiController::class, 'getTags']);
  Route::get('tags/{slug}', [\App\Http\Controllers\CanvasUiController::class, 'showTag']);
  Route::get('tags/{slug}/posts', [\App\Http\Controllers\CanvasUiController::class, 'getPostsForTag']);

  Route::get('tag', [\App\Http\Controllers\CanvasUiController::class, 'getTopics']);
  Route::get('topics/{slug}', [\App\Http\Controllers\CanvasUiController::class, 'showTopic']);
  Route::get('topics/{slug}/posts', [\App\Http\Controllers\CanvasUiController::class, 'getPostsForTopic']);

  Route::get('users/{id}', [\App\Http\Controllers\CanvasUiController::class, 'showUser']);
  Route::get('users/{id}/posts', [\App\Http\Controllers\CanvasUiController::class, 'getPostsForUser']);
  });
  Route::get('/{view?}', [\App\Http\Controllers\CanvasUiController::class, 'index'])
    ->where('view', '(.*)')
    ->name('canvas-ui');
});
// */

// Honeypots
Route::get('/wp-admin/load-styles.php',function() {
  return response()->view('wp-load_styles')->header('Content-Type','text/css');
});
Route::get('/wp-login.php', 'WordpressController@index');
Route::post('/wp-login.php', 'WordpressController@ban');
Route::redirect('/wp-admin','/wp-login.php');