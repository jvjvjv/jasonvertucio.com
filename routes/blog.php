<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CommentReplyNotificationController;

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

    $route->put('/{slug}/comments/{comment}', [CommentController::class, 'update'])
        ->middleware('auth')
        ->name('comments.update');
    $route->delete('/{slug}/comments/{comment}', [CommentController::class, 'destroy'])
        ->middleware('auth')
        ->name('comments.destroy');
    $route->post('/{slug}/comments/{comment}/spam', [CommentController::class, 'markSpam'])
        ->middleware(['auth', 'can:moderate-comments'])
        ->name('comments.spam');

    $route->get('/{slug}', [BlogController::class, 'post'])->name('post');
});

// Reply-email confirmation and unsubscribe, reached from links in emails. The
// signature is the authorization; every state change needs a POST, so a mail
// scanner prefetching a link changes nothing.
Route::middleware(['signed:relative', 'throttle:60,1'])->prefix('comments/{comment}/reply-emails')->group(function () {
    Route::get('/confirm', [CommentReplyNotificationController::class, 'showConfirm'])
        ->name('comments.reply-emails.confirm');
    Route::post('/confirm', [CommentReplyNotificationController::class, 'confirm'])
        ->name('comments.reply-emails.confirm.store');

    Route::post('/resend', [CommentReplyNotificationController::class, 'resend'])
        ->name('comments.reply-emails.resend');

    Route::get('/unsubscribe', [CommentReplyNotificationController::class, 'showUnsubscribe'])
        ->withTrashed()
        ->name('comments.reply-emails.unsubscribe');
    Route::post('/unsubscribe', [CommentReplyNotificationController::class, 'unsubscribe'])
        ->withTrashed()
        ->name('comments.reply-emails.unsubscribe.store');
});
