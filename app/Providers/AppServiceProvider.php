<?php

namespace App\Providers;

use App\Contracts\ResumeDataServiceContract;
use App\Http\Requests\Admin\StoreAiChatBotRequest;
use App\Http\Requests\Admin\UpdateAiChatBotRequest;
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
use Jvjvjv\CodeTalker\Http\Requests\Admin\StoreAiChatBotRequest as BaseStoreAiChatBotRequest;
use Jvjvjv\CodeTalker\Http\Requests\Admin\UpdateAiChatBotRequest as BaseUpdateAiChatBotRequest;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Admin\AiChatBotController extends the package controller, so its
        // store()/update() must type-hint the package form requests (PHP
        // forbids narrowing a parameter type). These bindings make the
        // container hand back the host subclasses, which add the
        // `allowed_roles` validation rules the host needs.
        $this->app->bind(BaseStoreAiChatBotRequest::class, StoreAiChatBotRequest::class);
        $this->app->bind(BaseUpdateAiChatBotRequest::class, UpdateAiChatBotRequest::class);
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Comment::observe(CommentObserver::class);

        // Workaround for a bspdx/keystone bug: KeystoneServiceProvider::register()
        // writes `passkeys.models.passkey` before spatie/laravel-passkeys merges its
        // own config. That merge is a shallow array_merge, so Keystone's single-key
        // `models` array replaces Spatie's wholesale and `authenticatable` is lost,
        // leaving Config::getAuthenticatableModel() to blow up on null. boot() runs
        // after every register(), so restoring the key here is safe.
        config(['passkeys.models.authenticatable' => config('keystone.user.model')]);

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
