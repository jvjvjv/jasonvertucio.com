<?php

use App\Http\Controllers\BlogController;

Route::group(['prefix' => 'blog'], function ($route) {
    $route->get('/', [BlogController::class, 'index'])->name('blog');

    $route->get('/topics', [BlogController::class, 'topics'])->name('topics');
    $route->get('/tags', [BlogController::class, 'tags'])->name('tags');

    $route->get('/topics/{slug}', [BlogController::class, 'topicList'])->name('topicList');
    $route->get('/tags/{slug}', [BlogController::class, 'tagList'])->name('tagList');

    $route->get('/{slug}', [BlogController::class, 'post'])->name('post');
});
