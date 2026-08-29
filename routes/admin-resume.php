<?php

use App\Http\Controllers\Admin\JobUrlParseController;
use App\Http\Controllers\Admin\ResumeEditorController;
use App\Http\Controllers\Admin\ResumeMetricsController;
use App\Http\Controllers\Admin\TargetedResumeController;
use App\Http\Middleware\HandleInertiaRequests;

// Resume editor routes - requires auth + edit-resume permission
Route::middleware(['auth', 'can:edit-resume', HandleInertiaRequests::class])
    ->prefix('admin/resume')
    ->name('admin.resume.')
    ->group(function () {
        Route::get('/editor', [ResumeEditorController::class, 'edit'])->name('editor');

        // AI-persona resume-edit candidate review
        Route::post('/candidates/{candidate}/approve', [ResumeEditorController::class, 'approveCandidate'])->name('candidates.approve');
        Route::post('/candidates/{candidate}/reject', [ResumeEditorController::class, 'rejectCandidate'])->name('candidates.reject');

        // Application Metrics
        Route::get('/metrics', [ResumeMetricsController::class, 'index'])->name('metrics');

        // Targeted Resume Builder
        Route::get('/targeted-builder', [TargetedResumeController::class, 'index'])->name('targeted.index');
        Route::get('/targeted-builder/new', [TargetedResumeController::class, 'create'])->name('targeted.create');

        Route::get('/targeted-builder/{conversation}', [TargetedResumeController::class, 'show'])->name('targeted.show');
        Route::put('/targeted-builder/{conversation}/metadata', [TargetedResumeController::class, 'updateMetadata'])->name('targeted.update-metadata');

        Route::post('/targeted-builder/{conversation}/pass', [TargetedResumeController::class, 'pass'])->name('targeted.pass');
        Route::delete('/targeted-builder/{conversation}', [TargetedResumeController::class, 'destroy'])->name('targeted.destroy');

        // Job URL Parsing
        Route::post('/targeted-builder/parser/{parser}/confirm', [JobUrlParseController::class, 'confirmParser'])->name('targeted.parser.confirm');
        Route::post('/targeted-builder/parser/{parser}/reject', [JobUrlParseController::class, 'rejectParser'])->name('targeted.parser.reject');
        Route::get('/targeted-resume/{targetedResume}/download/{format}', [TargetedResumeController::class, 'download'])->name('targeted.download');
        Route::post('/targeted-resume/{targetedResume}/regenerate', [TargetedResumeController::class, 'regenerate'])->name('targeted.regenerate');
    });
