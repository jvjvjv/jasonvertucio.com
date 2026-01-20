<?php

namespace App\Providers;

use App\Http\Middleware\SyncCanvasAuth;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class CanvasServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Override Canvas's auth driver to use our users table instead of canvas_users
        Config::set('auth.guards.canvas', [
            'driver' => 'session',
            'provider' => 'users',
        ]);

        // Add SyncCanvasAuth to the web middleware group so it runs after session is started
        $router = $this->app->make(Router::class);
        $router->pushMiddlewareToGroup('web', SyncCanvasAuth::class);

        $this->app->booted(function () {
            $schedule = resolve(Schedule::class);
            $schedule->command('canvas:digest')
                ->weekly()
                ->mondays()
                ->timezone(config('app.timezone'))
                ->at('08:00')
                ->when(function () {
                    return config('canvas.mail.enabled');
                });
        });
    }
}
