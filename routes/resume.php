<?php

use App\Http\Controllers\ResumeController;

use App\Http\Middleware\ResumeShareCodeMiddleware;


// Manual code entry page for unauthenticated users
Route::get('/resume/enter-code', [ResumeController::class, 'enterCode'])->name('resume.enter-code');

// Resume routes - supports both authenticated users and share codes
Route::middleware([ResumeShareCodeMiddleware::class])->prefix('resume')->group(function () {
    Route::get('/', [ResumeController::class, 'index'])->name('resume.index');

    // Direct download of pre-generated DOCX
    Route::prefix('/download')->name('resume.download.')->group(function () {
        Route::get('/', [ResumeController::class, 'download'])->name('index');
        Route::get('/docx', [ResumeController::class, 'downloadDocx'])->name('docx');
        Route::get('/pdf', [ResumeController::class, 'downloadPdf'])->name('pdf');
    });
});

