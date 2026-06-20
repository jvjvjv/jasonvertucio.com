<?php

namespace App\Providers;

use App\View\Components\TechSkill;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class BladeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Blade::componentNamespace('Illuminate\Mail\Mailables\Components', 'mail');

        Blade::if('ifwinkauthenticated', function () {
            $auth = Auth::guard('wink');

            return $auth->check();
        });

        Blade::if('ifcanvasauthenticated', function () {
            $auth = Auth::guard('canvas');

            return $auth->check();
        });

        Blade::if('ifauthenticated', function () {
            return Auth::check();
        });
    }
}
