<?php

namespace App\Http\Controllers\Auth\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Shared rate-limiting helpers for the passwordless/passkey authentication
 * endpoints, backed by Laravel's RateLimiter.
 */
trait ThrottlesAuthentication
{
    protected int $lockoutDurationMinutes = 1;

    /**
     * Build a throttle key scoped to the action plus a stable identifier
     * (authenticated user, else submitted email) and the client IP.
     */
    protected function throttleKey(Request $request, string $action): string
    {
        $identifier = $request->user()?->getAuthIdentifier()
            ?? $request->input('email')
            ?? '';

        return Str::transliterate(Str::lower($action.'|'.$identifier.'|'.$request->ip()));
    }

    /**
     * Determine whether the given key has exceeded the configured ceiling.
     */
    protected function hasTooManyAttempts(string $key, int $maxAttempts): bool
    {
        return RateLimiter::tooManyAttempts($key, $maxAttempts);
    }

    /**
     * Record a failed attempt against the key.
     */
    protected function recordFailedAttempt(string $key): void
    {
        RateLimiter::hit($key, $this->lockoutDurationMinutes * 60);
    }

    /**
     * Clear the attempt counter for the key (call on success).
     */
    protected function clearAttempts(string $key): void
    {
        RateLimiter::clear($key);
    }

    /**
     * Seconds until the key is available again.
     */
    protected function secondsUntilAvailable(string $key): int
    {
        return RateLimiter::availableIn($key);
    }

    /**
     * Build a consistent "too many attempts" (429) response. The throttle key
     * is rebuilt from the same action to report the remaining lockout time.
     */
    protected function tooManyAttemptsResponse(
        Request $request,
        string $errorField,
        string $action = ''
    ): JsonResponse|RedirectResponse {
        $seconds = $action !== ''
            ? $this->secondsUntilAvailable($this->throttleKey($request, $action))
            : 0;

        $message = 'Too many attempts. Please try again'
            .($seconds > 0 ? " in {$seconds} seconds." : ' later.');

        if ($request->wantsJson()) {
            return response()->json(['message' => $message], 429);
        }

        return back()->withErrors([$errorField => $message]);
    }
}
