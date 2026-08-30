<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Display the user's profile page.
     */
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('profile.show', [
            'user' => $user,
            'hasTwoFactor' => $user->hasTwoFactorEnabled(),
            'hasPasskeys' => $user->hasPasskeysRegistered(),
            'passkeys' => $user->passkeys ?? collect(),
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    /**
     * Update the user's authentication preferences.
     */
    public function updateAuthPreferences(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'allow_passkey_login' => 'boolean',
            'allow_totp_login' => 'boolean',
            'require_password' => 'boolean',
        ]);

        $preferences = [
            'allow_passkey_login' => $request->boolean('allow_passkey_login'),
            'allow_totp_login' => $request->boolean('allow_totp_login'),
            'require_password' => $request->boolean('require_password'),
        ];

        $hasPasskey = $user->hasPasskeysRegistered();
        $hasTwoFactor = $user->hasTwoFactorEnabled();

        $willHaveMethod = $preferences['require_password'] ||
            ($preferences['allow_passkey_login'] && $hasPasskey) ||
            ($preferences['allow_totp_login'] && $hasTwoFactor);

        if (! $willHaveMethod) {
            return back()->withErrors([
                'auth_preferences' => 'You must have at least one authentication method enabled.',
            ]);
        }

        if ($preferences['allow_passkey_login'] && ! $hasPasskey) {
            return back()->withErrors([
                'allow_passkey_login' => 'You must register a passkey before enabling passkey login.',
            ]);
        }

        if ($preferences['allow_totp_login'] && ! $hasTwoFactor) {
            return back()->withErrors([
                'allow_totp_login' => 'You must enable two-factor authentication before enabling TOTP login.',
            ]);
        }

        $user->update($preferences);

        $request->session()->regenerate();

        return back()->with('status', 'auth-preferences-updated');
    }

    /**
     * Update whether the user sees tool call arguments and results in chat.
     *
     * The /profile routes are only `auth`-gated, not split by permission, so
     * this method enforces `manage-ai-tools` itself rather than relying on
     * route middleware.
     */
    public function updateToolVisibility(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can('manage-ai-tools'), 403);

        $request->validate([
            'show_tool_payloads' => 'boolean',
        ]);

        $user->update([
            'show_tool_payloads' => $request->boolean('show_tool_payloads'),
        ]);

        return back()->with('status', 'tool-visibility-updated');
    }
}
