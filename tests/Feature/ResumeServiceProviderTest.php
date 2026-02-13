<?php

namespace Tests\Feature;

use App\Contracts\ResumeDataServiceContract;
use App\Contracts\ResumeVersionServiceContract;
use App\Services\DatabaseResumeDataService;
use App\Services\DatabaseResumeVersionService;
use App\Services\JsonResumeDataService;
use App\Services\JsonResumeVersionService;
use Tests\TestCase;

class ResumeServiceProviderTest extends TestCase
{
    public function test_json_driver_resolves_json_data_service(): void
    {
        config(['resume.driver' => 'json']);

        // Clear singleton so it re-resolves
        $this->app->forgetInstance(ResumeDataServiceContract::class);

        $service = $this->app->make(ResumeDataServiceContract::class);

        $this->assertInstanceOf(JsonResumeDataService::class, $service);
    }

    public function test_json_driver_resolves_json_version_service(): void
    {
        config(['resume.driver' => 'json']);

        $this->app->forgetInstance(ResumeVersionServiceContract::class);

        $service = $this->app->make(ResumeVersionServiceContract::class);

        $this->assertInstanceOf(JsonResumeVersionService::class, $service);
    }

    public function test_database_driver_resolves_database_data_service(): void
    {
        config(['resume.driver' => 'database']);

        $this->app->forgetInstance(ResumeDataServiceContract::class);

        $service = $this->app->make(ResumeDataServiceContract::class);

        $this->assertInstanceOf(DatabaseResumeDataService::class, $service);
    }

    public function test_database_driver_resolves_database_version_service(): void
    {
        config(['resume.driver' => 'database']);

        $this->app->forgetInstance(ResumeVersionServiceContract::class);

        $service = $this->app->make(ResumeVersionServiceContract::class);

        $this->assertInstanceOf(DatabaseResumeVersionService::class, $service);
    }

    public function test_default_driver_is_json(): void
    {
        config(['resume.driver' => null]);

        $this->app->forgetInstance(ResumeDataServiceContract::class);

        $service = $this->app->make(ResumeDataServiceContract::class);

        $this->assertInstanceOf(JsonResumeDataService::class, $service);
    }
}
