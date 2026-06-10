<?php

namespace App\Providers;

use App\Contracts\ResumeDataServiceContract;
use App\Models\Comment;
use App\Observers\CommentObserver;
use App\Services\Mcp\TargetedResumeToolRegistry;
use App\Services\TargetedResumeService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Jvjvjv\CodeTalker\CodeTalkerServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Comment::observe(CommentObserver::class);

        Route::model('aiChatBot', \App\Models\AiChatBot::class);

        // Force HTTPS in local development when using local-ssl-proxy
        if (app()->environment('dev') && request()->getHost() === 'localhost') {
            URL::forceScheme('https');
        }

        // Register app-specific MCP tool directories with the CodeTalker package
        CodeTalkerServiceProvider::addToolDirectory(
            app_path('Services/Mcp/Tools'),
            'App\\Services\\Mcp\\Tools\\'
        );

        // Provide resume-specific parameter overrides to tool handlers
        CodeTalkerServiceProvider::registerToolParameterResolver(
            fn (): array => [
                'resumeDataService' => app(ResumeDataServiceContract::class),
                'targetedResumeService' => app(TargetedResumeService::class),
            ]
        );
    }
}
