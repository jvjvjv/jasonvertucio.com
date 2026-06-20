<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Jvjvjv\CodeTalker\Services\AiClientFactory;
use Jvjvjv\CodeTalker\Services\ClaudeService;

class ClaudeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/claude.php'), 'claude'
        );

        $this->app->singleton(ClaudeService::class, function ($app) {
            return new ClaudeService;
        });

        $this->app->singleton(AiClientFactory::class);
    }
}
