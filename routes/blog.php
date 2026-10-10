<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\CommentController;

Route::group(['prefix' => 'blog'], function ($route) {
    $route->get('/', [BlogController::class, 'index'])->name('blog');

    $route->get('/feed', [BlogController::class, 'feed'])->name('feed');

    $route->get('/topics', [BlogController::class, 'topics'])->name('topics');
    $route->get('/tags', [BlogController::class, 'tags'])->name('tags');

    $route->get('/topics/{slug}', [BlogController::class, 'topicList'])->name('topicList');
    $route->get('/tags/{slug}', [BlogController::class, 'tagList'])->name('tagList');

    $route->post('/{slug}/comments', [CommentController::class, 'store'])
        ->middleware('throttle:comments')
        ->name('comments.store');

    $route->post('/{slug}/comments/{comment}/spam', [CommentController::class, 'markSpam'])
        ->middleware(['auth', 'can:moderate-comments'])
        ->name('comments.spam');

    $route->get('/{slug}', [BlogController::class, 'post'])->name('post');
});
