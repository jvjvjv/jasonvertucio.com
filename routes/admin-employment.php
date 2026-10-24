<?php

use App\Http\Controllers\Admin\CompanyIntelController;
use App\Http\Middleware\HandleInertiaRequests;

// Employment pages that live under /admin/employment. The hub itself is in
// routes/admin.php; the older pages it links to keep their own URLs.
Route::middleware(['auth', 'can:edit-resume', HandleInertiaRequests::class])
    ->prefix('admin/employment')
    ->name('admin.employment.')
    ->group(function () {
        Route::get('/company-intel', [CompanyIntelController::class, 'index'])->name('company-intel.index');
        Route::get('/company-intel/{roleIntel}', [CompanyIntelController::class, 'show'])
            ->whereNumber('roleIntel')
            ->name('company-intel.show');
    });
