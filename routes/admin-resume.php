<?php

use App\Http\Controllers\Admin\ResumeEditorController;
use App\Http\Controllers\Admin\TargetedResumeController;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AiConversation;

// Resume editor routes - requires auth + edit-resume permission
Route::middleware(['auth', 'can:edit-resume', HandleInertiaRequests::class])
    ->prefix('admin/resume')
    ->name('admin.resume.')
    ->group(function () {
        Route::get('/editor', [ResumeEditorController::class, 'edit'])->name('editor');

        // AI-persona resume-edit candidate review
        Route::post('/candidates/{candidate}/approve', [ResumeEditorController::class, 'approveCandidate'])->name('candidates.approve');
        Route::post('/candidates/{candidate}/reject', [ResumeEditorController::class, 'rejectCandidate'])->name('candidates.reject');

        // Targeted Resumes (the documents themselves)
        Route::get('/targeted-resumes', [TargetedResumeController::class, 'index'])->name('targeted.index');
        Route::get('/targeted-resumes/{targetedResume}/edit', [TargetedResumeController::class, 'edit'])
            ->whereNumber('targetedResume')
            ->name('targeted.edit');
        Route::delete('/targeted-resumes/{targetedResume}', [TargetedResumeController::class, 'destroy'])
            ->whereNumber('targetedResume')
            ->name('targeted.destroy');
        Route::get('/targeted-resume/{targetedResume}/download/{format}', [TargetedResumeController::class, 'download'])
            ->whereNumber('targetedResume')
            ->name('targeted.download');
        Route::post('/targeted-resume/{targetedResume}/regenerate', [TargetedResumeController::class, 'regenerate'])
            ->whereNumber('targetedResume')
            ->name('targeted.regenerate');

        // The former Targeted Resume Builder pages, kept for bookmarks.
        Route::get('/targeted-builder', fn () => redirect()->route('admin.employment.applications.index', status: 301));
        Route::get('/targeted-builder/new', fn () => redirect()->route('admin.employment.applications.create', status: 301));
        Route::get('/targeted-builder/{conversation}', function (AiConversation $conversation) {
            $application = $conversation->application()->firstOrFail();

            return redirect()->route('admin.employment.applications.show', $application, 301);
        })->whereNumber('conversation');
    });
