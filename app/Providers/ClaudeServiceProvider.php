<?php

namespace App\Providers;

use App\Services\AiClientFactory;
use App\Services\ClaudeService;
use Illuminate\Support\ServiceProvider;

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
            return new ClaudeService();
        });

        $this->app->singleton(AiClientFactory::class);
    }
}
