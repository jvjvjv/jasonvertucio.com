<?php

use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\JobUrlParseController;
use App\Http\Controllers\Admin\ResumeEditorController;
use App\Http\Controllers\Admin\ResumeMetricsController;
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

        // Application Metrics
        Route::get('/metrics', [ResumeMetricsController::class, 'index'])->name('metrics');

        // Applications. {application} is numeric-only so the literal segments
        // (new, parser, parse-url) can never be taken for one.
        Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
        Route::get('/applications/new', [ApplicationController::class, 'create'])->name('applications.create');
        Route::get('/applications/{application}', [ApplicationController::class, 'show'])
            ->whereNumber('application')
            ->name('applications.show');
        Route::delete('/applications/{application}', [ApplicationController::class, 'destroy'])
            ->whereNumber('application')
            ->name('applications.destroy');

        // Job URL Parsing
        Route::post('/applications/parser/{parser}/confirm', [JobUrlParseController::class, 'confirmParser'])->name('applications.parser.confirm');
        Route::post('/applications/parser/{parser}/reject', [JobUrlParseController::class, 'rejectParser'])->name('applications.parser.reject');

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
        Route::get('/targeted-builder', fn () => redirect()->route('admin.resume.applications.index', status: 301));
        Route::get('/targeted-builder/new', fn () => redirect()->route('admin.resume.applications.create', status: 301));
        Route::get('/targeted-builder/{conversation}', function (AiConversation $conversation) {
            $application = $conversation->application()->firstOrFail();

            return redirect()->route('admin.resume.applications.show', $application, 301);
        })->whereNumber('conversation');
    });
