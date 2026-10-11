<?php

namespace App\Providers;

use App\Contracts\ResumeDataServiceContract;
use App\Events\MediaPlaybackMilestoneReached;
use App\Events\UserSecurityMethodRemoved;
use App\Listeners\AlertUserOfSecurityDowngrade;
use App\Listeners\Auth\DetectTwoFactorSecurityDowngrade;
use App\Listeners\FlushBlogFeedCache;
use App\Listeners\FlushPublicMcpBlogCache;
use App\Listeners\InvalidateCurrentlyWatchingCache;
use App\Listeners\LogSecurityAuditEntry;
use App\Listeners\LogSocialPostStub;
use App\Listeners\RecordMcpClientIdentity;
use App\Listeners\RecordRecentlyFinishedMedia;
use App\Models\AiChatBot;
use App\Models\Comment;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Observers\CommentObserver;
use App\Services\Mcp\PublicMcpCache;
use App\Services\Mcp\TargetedResumeToolRegistry;
use App\Services\TargetedResumeService;
use Canvas\Events\PostDeleted;
use Canvas\Events\PostPublished;
use Canvas\Events\PostUnpublished;
use Canvas\Events\PostUpdated;
use Illuminate\Cache\RateLimiting\Limit;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Jvjvjv\CodeTalker\CodeTalkerServiceProvider;
use Jvjvjv\CodeTalker\Services\Conversation\CodeTalkerConversationStore;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Mcp\Events\SessionInitialized;

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

        // The one definition of "may moderate comments". `moderate-comments`
        // is an ability, not a permission: Keystone's Gate::before answers
        // definitively for any ability matching a permission name, so a
        // permission must never be created with this name or it would
        // override the rule below.
        Gate::define('moderate-comments', fn (User $user): bool => $user->hasPermissionTo('manage-comments')
            || $user->hasPermissionTo('manage-blog'));

        RateLimiter::for('comments', function (Request $request) {
            return Limit::perMinute(config('comments.rate_limit_per_minute'))
                ->by($request->header('CF-Connecting-IP') ?? $request->ip());
        });

        // Public MCP endpoint. Sized to stop floods and systematic harvesting,
        // not to protect the database — repeated calls are served from cache,
        // so an ordinary session (initialize, tools/list, three tool calls)
        // must never be throttled.
        //
        // Each window needs its own `by()` value: ThrottleRequests derives the
        // cache key as md5($limiterName.$limit->key) with no per-limit index,
        // so two limits sharing a key would share one counter.
        RateLimiter::for('mcp', function (Request $request) {
            $user = $request->user();

            if ($user !== null) {
                $token = $user->currentAccessToken()?->getKey() ?? $user->getAuthIdentifier();

                return [
                    Limit::perMinute(config('mcp-server.limits.token.per_minute'))->by("mcp-token-minute:{$token}"),
                    Limit::perDay(config('mcp-server.limits.token.per_day'))->by("mcp-token-day:{$token}"),
                ];
            }

            $address = $request->header('CF-Connecting-IP') ?? $request->ip();

            return [
                Limit::perMinute(config('mcp-server.limits.anonymous.per_minute'))->by("mcp-anon-minute:{$address}"),
                Limit::perHour(config('mcp-server.limits.anonymous.per_hour'))->by("mcp-anon-hour:{$address}"),
            ];
        });

        Route::model('aiChatBot', AiChatBot::class);

        Event::listen([
            PostPublished::class,
            PostUpdated::class,
            PostUnpublished::class,
            PostDeleted::class,
        ], FlushBlogFeedCache::class);

        Event::listen([
            PostPublished::class,
            PostUpdated::class,
            PostUnpublished::class,
            PostDeleted::class,
        ], FlushPublicMcpBlogCache::class);

        // The public MCP endpoint caches the resume; a newly published version
        // must be visible on the next call rather than after a TTL.
        $flushResumeCache = static function (): void {
            PublicMcpCache::flush(PublicMcpCache::GROUP_RESUME);
        };

        ResumeVersion::saved($flushResumeCache);
        ResumeVersion::deleted($flushResumeCache);

        Event::listen(SessionInitialized::class, RecordMcpClientIdentity::class);

        Event::listen(MediaPlaybackMilestoneReached::class, RecordRecentlyFinishedMedia::class);
        Event::listen(MediaPlaybackMilestoneReached::class, InvalidateCurrentlyWatchingCache::class);
        Event::listen(MediaPlaybackMilestoneReached::class, LogSocialPostStub::class);

        Event::listen(TwoFactorAuthenticationDisabled::class, DetectTwoFactorSecurityDowngrade::class);

        Event::listen(UserSecurityMethodRemoved::class, LogSecurityAuditEntry::class);
        Event::listen(UserSecurityMethodRemoved::class, AlertUserOfSecurityDowngrade::class);

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
