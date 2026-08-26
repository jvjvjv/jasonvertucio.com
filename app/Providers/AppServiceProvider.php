<?php

namespace App\Providers;

use App\Contracts\ResumeDataServiceContract;
use App\Models\AiChatBot;
use App\Models\Comment;
use App\Observers\CommentObserver;
use App\Services\Mcp\TargetedResumeToolRegistry;
use App\Services\TargetedResumeService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Jvjvjv\CodeTalker\CodeTalkerServiceProvider;
use Jvjvjv\CodeTalker\Services\Conversation\CodeTalkerConversationStore;
use Laravel\Ai\Contracts\ConversationStore;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Package providers are auto-discovered and registered alphabetically
        // (Illuminate\Foundation\PackageManifest), so laravel/ai's own
        // register() runs after jvjvjv/code-talker's and re-binds
        // ConversationStore to its default DatabaseConversationStore,
        // clobbering the package's CodeTalkerConversationStore binding.
        // AppServiceProvider registers last (config('app.providers') is
        // appended after the package manifest), so rebinding here wins.
        $this->app->singleton(ConversationStore::class, CodeTalkerConversationStore::class);
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Comment::observe(CommentObserver::class);

        Route::model('aiChatBot', AiChatBot::class);

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
