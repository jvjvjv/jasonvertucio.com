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
     *
     * Credentials and security settings live at bspdx.com, the only way to
     * sign in, so this page shows the linked identity and points there.
     */
    public function show(Request $request): View
    {
        $user = $request->user();

        return view('profile.show', [
            'user' => $user,
            'bspdxIdentity' => $user->bspdxIdentity(),
            'bspdxAccountUrl' => rtrim((string) config('services.bspdx.base_url'), '/').'/profile',
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
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
