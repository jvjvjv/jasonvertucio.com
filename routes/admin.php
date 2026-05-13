<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\CoverLetterController;
use App\Http\Controllers\Admin\ResumeShareCodeController;
use App\Http\Controllers\Admin\MailPreviewController;
use App\Http\Controllers\Admin\SiteSettingsController;

// Admin routes - requires auth + manage-unauthenticated-viewers permission
Route::middleware(['auth', 'can:manage-unauthenticated-viewers', \App\Http\Middleware\HandleInertiaRequests::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');

        // Resume management hub
        Route::get('/resume', [AdminController::class, 'resumeHub'])->name('resume.index');

        // Share codes management
        Route::get('/resume/codes', [ResumeShareCodeController::class, 'index'])->name('resume.codes.index');
        Route::post('/resume/codes', [ResumeShareCodeController::class, 'store'])->name('resume.codes.store');
        Route::delete('/resume/codes/{code}', [ResumeShareCodeController::class, 'destroy'])->name('resume.codes.destroy');

        // Cover letter management
        Route::get('/cover-letters', [CoverLetterController::class, 'index'])->name('cover-letters.index');
        Route::get('/cover-letters/new', [CoverLetterController::class, 'create'])->name('cover-letters.create');
        Route::post('/cover-letters', [CoverLetterController::class, 'store'])->name('cover-letters.store');
        Route::get('/cover-letters/{coverLetter}', [CoverLetterController::class, 'edit'])->name('cover-letters.edit');
        Route::put('/cover-letters/{coverLetter}', [CoverLetterController::class, 'update'])->name('cover-letters.update');
        Route::delete('/cover-letters/{coverLetter}', [CoverLetterController::class, 'destroy'])->name('cover-letters.destroy');
        Route::get('/cover-letters/{coverLetter}/preview', [CoverLetterController::class, 'preview'])->name('cover-letters.preview');
        Route::get('/cover-letters/{coverLetter}/download/docx', [CoverLetterController::class, 'downloadDocx'])->name('cover-letters.download.docx');
        Route::get('/cover-letters/{coverLetter}/download/pdf', [CoverLetterController::class, 'downloadPdf'])->name('cover-letters.download.pdf');

        // Mail preview routes
        Route::get('/mail-preview', [MailPreviewController::class, 'index'])->name('mail-preview.index');
        Route::get('/mail-preview/{mailable}', [MailPreviewController::class, 'show'])->name('mail-preview.show');
        Route::get('/mail-preview/{mailable}/preview', [MailPreviewController::class, 'preview'])->name('mail-preview.preview');

        // Site settings (navigation links)
        Route::get('/site-settings', [SiteSettingsController::class, 'edit'])->name('site-settings.edit');
        Route::post('/site-settings', [SiteSettingsController::class, 'update'])->name('site-settings.update');
    });
