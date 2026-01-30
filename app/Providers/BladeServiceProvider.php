<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use App\View\Components\TechSkill;
use Illuminate\Support\Facades\Auth;

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
