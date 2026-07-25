<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleChatInertiaRequests extends Middleware
{
    protected $rootView = 'chat';

    /**
     * Route names for the bot conversation page, which renders without site chrome.
     *
     * @var array<int, string>
     */
    protected array $bareViewRouteNames = [
        'chat-bots.chat.show',
        'chat-bots.root.show',
    ];

    public function rootView(Request $request): string
    {
        $routeName = $request->route()?->getName();

        if ($routeName !== null && in_array($routeName, $this->bareViewRouteNames, true)) {
            return 'chat-bare';
        }

        return $this->rootView;
    }

    public function version(Request $request): ?string
    {
        $base = parent::version($request);

        return 'chat:'.($base ?? 'default');
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
            ],
            'session' => [
                'expiresAt' => now()->addMinutes((int) config('session.lifetime'))->toIso8601String(),
            ],
            'flash' => [
                'success' => fn () => $request->hasSession() ? $request->session()->get('success') : null,
                'error' => fn () => $request->hasSession() ? $request->session()->get('error') : null,
            ],
        ];
    }
}
