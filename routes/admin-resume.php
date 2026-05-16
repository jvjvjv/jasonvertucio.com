<?php

use App\Http\Controllers\Admin\JobUrlParseController;
use App\Http\Controllers\Admin\ResumeEditorController;
use App\Http\Controllers\Admin\TargetedResumeController;

// Resume editor routes - requires auth + edit-resume permission
Route::middleware(['auth', 'can:edit-resume', \App\Http\Middleware\HandleInertiaRequests::class])
    ->prefix('admin/resume')
    ->name('admin.resume.')
    ->group(function () {
        Route::get('/editor', [ResumeEditorController::class, 'edit'])->name('editor');
        Route::post('/editor', [ResumeEditorController::class, 'update'])->name('editor.save');

        // Targeted Resume Builder
        Route::get('/targeted-builder', [TargetedResumeController::class, 'index'])->name('targeted.index');
        Route::get('/targeted-builder/new', [TargetedResumeController::class, 'create'])->name('targeted.create');
        Route::post('/targeted-builder/start', [TargetedResumeController::class, 'start'])->name('targeted.start');
        Route::get('/targeted-builder/{conversation}', [TargetedResumeController::class, 'show'])->name('targeted.show');
        Route::put('/targeted-builder/{conversation}/metadata', [TargetedResumeController::class, 'updateMetadata'])->name('targeted.update-metadata');
        Route::post('/targeted-builder/{conversation}/chat', [TargetedResumeController::class, 'chat'])->name('targeted.chat');
        Route::post('/targeted-builder/{conversation}/finalize', [TargetedResumeController::class, 'finalize'])->name('targeted.finalize');
        Route::post('/targeted-builder/{conversation}/finalize-cover-letter', [TargetedResumeController::class, 'finalizeCoverLetter'])->name('targeted.finalize-cover-letter');
        Route::post('/targeted-builder/{conversation}/pass', [TargetedResumeController::class, 'pass'])->name('targeted.pass');
        Route::post('/targeted-builder/{conversation}/status-update', [TargetedResumeController::class, 'addStatusUpdate'])->name('targeted.status-update');
        Route::delete('/targeted-builder/{conversation}', [TargetedResumeController::class, 'destroy'])->name('targeted.destroy');

        // Job URL Parsing
        Route::post('/targeted-builder/parse-url', [JobUrlParseController::class, 'parse'])->name('targeted.parse-url');
        Route::post('/targeted-builder/parser/{parser}/confirm', [JobUrlParseController::class, 'confirmParser'])->name('targeted.parser.confirm');
        Route::post('/targeted-builder/parser/{parser}/reject', [JobUrlParseController::class, 'rejectParser'])->name('targeted.parser.reject');
        Route::post('/targeted-builder/parser/{parser}/reparse', [JobUrlParseController::class, 'reparse'])->name('targeted.parser.reparse');
        Route::get('/targeted-resume/{targetedResume}/download/{format}', [TargetedResumeController::class, 'download'])->name('targeted.download');
        Route::post('/targeted-resume/{targetedResume}/regenerate', [TargetedResumeController::class, 'regenerate'])->name('targeted.regenerate');
    });
