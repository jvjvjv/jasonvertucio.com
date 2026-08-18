<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\ThrottlesAuthentication;
use App\Http\Controllers\Controller;
use App\Services\Auth\PasskeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PasskeyController extends Controller
{
    use ThrottlesAuthentication;

    public function __construct(
        private PasskeyService $passkeyService
    ) {}

    /**
     * Generate passkey registration options for the authenticated user.
     */
    public function registerOptions(Request $request): JsonResponse
    {
        $optionsJson = $this->passkeyService->generateRegisterOptions($request->user());

        return response()->json(json_decode($optionsJson, true));
    }

    /**
     * Store a new passkey for the authenticated user.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'credential' => ['required'],
            'options' => ['required'],
        ]);

        $credentialJson = is_string($validated['credential'])
            ? $validated['credential']
            : json_encode($validated['credential']);

        $optionsJson = is_string($validated['options'])
            ? $validated['options']
            : json_encode($validated['options']);

        try {
            $this->passkeyService->storePasskey(
                user: $request->user(),
                passkeyJson: $credentialJson,
                optionsJson: $optionsJson,
                additionalProperties: ['name' => $validated['name']],
            );

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Passkey registered successfully.']);
            }

            return redirect()->back()->with('status', 'passkey-registered');
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Failed to register passkey: '.$e->getMessage()], 422);
            }

            return redirect()->back()->withErrors([
                'passkey' => 'Failed to register passkey. Please try again.',
            ]);
        }
    }

    /**
     * Delete a passkey belonging to the authenticated user.
     */
    public function destroy(Request $request, string $passkeyId): RedirectResponse|JsonResponse
    {
        $passkey = $request->user()->passkeys()->findOrFail($passkeyId);
        $passkey->delete();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Passkey deleted successfully.']);
        }

        return redirect()->back()->with('status', 'passkey-deleted');
    }

    /**
     * Get all passkeys for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $passkeys = $request->user()->passkeys()->get()->map(fn ($passkey) => [
            'id' => $passkey->id,
            'name' => $passkey->name,
            'created_at' => $passkey->created_at->toDateTimeString(),
            'last_used_at' => $passkey->last_used_at?->toDateTimeString(),
        ]);

        return response()->json(['passkeys' => $passkeys]);
    }

    /**
     * Generate passkey authentication options for the login page.
     */
    public function loginOptions(Request $request): JsonResponse
    {
        $optionsJson = $this->passkeyService->generateAuthenticationOptions();

        return response()->json(json_decode($optionsJson, true));
    }

    /**
     * Authenticate a user using a passkey.
     */
    public function authenticate(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'credential' => ['required'],
            'options' => ['required'],
        ]);

        $throttleKey = $this->throttleKey($request, 'passkey-auth');
        $maxAttempts = 3;

        if ($this->hasTooManyAttempts($throttleKey, $maxAttempts)) {
            return $this->tooManyAttemptsResponse($request, 'passkey', 'passkey-auth');
        }

        $credentialJson = is_string($validated['credential'])
            ? $validated['credential']
            : json_encode($validated['credential']);

        $optionsJson = is_string($validated['options'])
            ? $validated['options']
            : json_encode($validated['options']);

        try {
            $passkey = $this->passkeyService->findPasskeyToAuthenticate($credentialJson, $optionsJson);

            if (! $passkey) {
                throw new \Exception('Invalid passkey credential.');
            }

            $user = $this->passkeyService->getAuthenticatableFromPasskey($passkey);

            $this->clearAttempts($throttleKey);

            auth()->login($user, remember: true);
            $request->session()->regenerate();

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Authentication successful.',
                    'redirect' => config('fortify.home', '/'),
                ]);
            }

            return redirect()->intended(config('fortify.home', '/'));
        } catch (\Exception $e) {
            $this->recordFailedAttempt($throttleKey);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Authentication failed: '.$e->getMessage()], 401);
            }

            return redirect()->back()->withErrors([
                'passkey' => 'Authentication failed. Please try again.',
            ]);
        }
    }
}
