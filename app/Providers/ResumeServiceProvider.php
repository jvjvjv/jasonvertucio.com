<?php

namespace App\Providers;

use App\Contracts\ResumeDataServiceContract;
use App\Contracts\ResumeVersionServiceContract;
use App\Services\DatabaseResumeDataService;
use App\Services\DatabaseResumeVersionService;
use App\Services\JsonResumeDataService;
use App\Services\JsonResumeVersionService;
use Illuminate\Support\ServiceProvider;

class ResumeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ResumeDataServiceContract::class, function ($app) {
            return match (config('resume.driver', 'json')) {
                'database' => $app->make(DatabaseResumeDataService::class),
                default => $app->make(JsonResumeDataService::class),
            };
        });

        $this->app->singleton(ResumeVersionServiceContract::class, function ($app) {
            return match (config('resume.driver', 'json')) {
                'database' => $app->make(DatabaseResumeVersionService::class),
                default => $app->make(JsonResumeVersionService::class),
            };
        });
    }
}
