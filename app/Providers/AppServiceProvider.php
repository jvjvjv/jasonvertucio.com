<?php

namespace App\Providers;

use App\Models\Comment;
use App\Observers\CommentObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
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
        Schema::defaultStringLength(191);
        Comment::observe(CommentObserver::class);

        // Force HTTPS in local development when using local-ssl-proxy
        if (app()->environment('dev') && request()->getHost() === 'localhost') {
            URL::forceScheme('https');
        }
    }
}
