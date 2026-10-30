<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * bspdx.com is the only way to sign in, so none of Fortify's routes
     * (password login, registration, reset, two-factor) are registered.
     * routes/auth.php reuses its logout controller.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
    }
}
