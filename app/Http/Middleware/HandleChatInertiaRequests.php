<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleChatInertiaRequests extends Middleware
{
    protected $rootView = 'chat';

    public function version(Request $request): ?string
    {
        $base = parent::version($request);
        return 'chat:' . ($base ?? 'default');
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
            'flash' => [
                'success' => fn() => $request->hasSession() ? $request->session()->get('success') : null,
                'error' => fn() => $request->hasSession() ? $request->session()->get('error') : null,
            ],
        ];
    }
}
