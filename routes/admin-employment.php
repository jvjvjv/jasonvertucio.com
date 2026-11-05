<?php

use App\Http\Controllers\Admin\ApplicationController;
use App\Http\Controllers\Admin\CompanyIntelController;
use App\Http\Controllers\Admin\JobUrlParseController;
use App\Http\Controllers\Admin\ResumeMetricsController;
use App\Http\Middleware\HandleInertiaRequests;

// Employment pages that live under /admin/employment: Applications,
// Application Metrics and Company Intel. The hub itself is in routes/admin.php;
// Resume Management and Cover Letters keep their own URLs.
Route::middleware(['auth', 'can:edit-resume', HandleInertiaRequests::class])
    ->prefix('admin/employment')
    ->name('admin.employment.')
    ->group(function () {
        // Applications. {application} is numeric-only so the literal segments
        // (new, parser) can never be taken for one.
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

        // Application Metrics
        Route::get('/metrics', [ResumeMetricsController::class, 'index'])->name('metrics');

        Route::get('/company-intel', [CompanyIntelController::class, 'index'])->name('company-intel.index');
        Route::get('/company-intel/{roleIntel}', [CompanyIntelController::class, 'show'])
            ->whereNumber('roleIntel')
            ->name('company-intel.show');
    });
