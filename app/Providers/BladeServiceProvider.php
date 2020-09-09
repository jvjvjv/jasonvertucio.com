<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Auth;

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
      Blade::if('ifwinkauthenticated', function () {
          $auth = Auth::guard('wink');
          return $auth->check();
      });

      Blade::if('ifauthenticated', function () {
          return Auth::check();
      });
    }
}
