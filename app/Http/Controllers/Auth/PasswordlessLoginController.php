<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\ThrottlesAuthentication;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class PasswordlessLoginController extends Controller
{
    use ThrottlesAuthentication;

    public function __construct(
        private TwoFactorAuthenticationProvider $twoFactorProvider
    ) {}

    /**
     * Authenticate using TOTP code only (passwordless).
     *
     * This allows users who have enabled TOTP-only login to authenticate
     * using just their email and authenticator code.
     */
    public function authenticateWithTotp(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'totp_code' => 'required|string|size:6',
        ]);

        $throttleKey = $this->throttleKey($request, 'totp-login');
        $maxAttempts = 5;

        if ($this->hasTooManyAttempts($throttleKey, $maxAttempts)) {
            return $this->tooManyAttemptsResponse($request, 'email', 'totp-login');
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! $user->allow_totp_login || ! $user->hasTwoFactorEnabled()) {
            $this->recordFailedAttempt($throttleKey);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invalid credentials.'], 401);
            }

            return back()->withErrors(['email' => 'Invalid credentials.']);
        }

        $valid = $this->twoFactorProvider->verify(
            decrypt($user->two_factor_secret),
            $request->totp_code
        );

        if (! $valid) {
            $this->recordFailedAttempt($throttleKey);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invalid authentication code.'], 401);
            }

            return back()->withErrors(['totp_code' => 'Invalid authentication code.']);
        }

        $this->clearAttempts($throttleKey);

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Authentication successful.',
                'redirect' => config('fortify.home', '/'),
            ]);
        }

        return redirect()->intended(config('fortify.home', '/'));
    }
}
