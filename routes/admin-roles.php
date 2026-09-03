<?php

use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Middleware\HandleInertiaRequests;

// Roles & permissions management - requires auth, then a per-action Keystone
// permission. The permissions themselves are seeded by AuthKitSeeder.
Route::middleware(['auth', HandleInertiaRequests::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/roles-permissions', [RolePermissionController::class, 'index'])
            ->middleware(['can:view-roles', 'can:view-permissions'])
            ->name('roles-permissions.index');

        // Roles CRUD
        Route::post('/roles', [RolePermissionController::class, 'storeRole'])
            ->middleware('can:create-roles')
            ->name('roles.store');
        Route::put('/roles/{role}', [RolePermissionController::class, 'updateRole'])
            ->middleware('can:edit-roles')
            ->name('roles.update');
        Route::delete('/roles/{role}', [RolePermissionController::class, 'destroyRole'])
            ->middleware('can:delete-roles')
            ->name('roles.destroy');

        // Assign permissions to a role
        Route::put('/roles/{role}/permissions', [RolePermissionController::class, 'syncRolePermissions'])
            ->middleware('can:assign-permissions')
            ->name('roles.permissions.sync');

        // Permissions CRUD
        Route::post('/permissions', [RolePermissionController::class, 'storePermission'])
            ->middleware('can:create-permissions')
            ->name('permissions.store');
        Route::put('/permissions/{permission}', [RolePermissionController::class, 'updatePermission'])
            ->middleware('can:edit-permissions')
            ->name('permissions.update');
        Route::delete('/permissions/{permission}', [RolePermissionController::class, 'destroyPermission'])
            ->middleware('can:delete-permissions')
            ->name('permissions.destroy');
    });
