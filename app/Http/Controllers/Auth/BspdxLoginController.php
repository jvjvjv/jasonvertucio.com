<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\BspdxSignInException;
use App\Http\Controllers\Controller;
use App\Services\Auth\BspdxAccountResolver;
use App\Services\Auth\BspdxOAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * "Sign in with bspdx.com": the authorization-code flow with PKCE.
 */
class BspdxLoginController extends Controller
{
    private const SESSION_STATE = 'bspdx.state';

    private const SESSION_VERIFIER = 'bspdx.code_verifier';

    public function __construct(
        private BspdxOAuthClient $client,
        private BspdxAccountResolver $resolver,
    ) {}

    /**
     * Send the browser to bspdx.com to sign in.
     */
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless($this->client->isAvailable(), 404);

        $state = Str::random(40);
        $verifier = Str::random(96);

        $request->session()->put([
            self::SESSION_STATE => $state,
            self::SESSION_VERIFIER => $verifier,
        ]);

        return redirect()->away($this->client->authorizationUrl($state, $verifier));
    }

    /**
     * Complete the sign-in when bspdx.com sends the browser back.
     */
    public function callback(Request $request): RedirectResponse
    {
        abort_unless($this->client->isAvailable(), 404);

        // Pulled, not read: a state and verifier are good for one attempt.
        $expectedState = $request->session()->pull(self::SESSION_STATE);
        $verifier = $request->session()->pull(self::SESSION_VERIFIER);

        try {
            if ($request->filled('error')) {
                throw BspdxSignInException::failed('bspdx.com returned error: '.$request->string('error'));
            }

            $state = $request->query('state');

            if (! is_string($expectedState) || ! is_string($state) || ! hash_equals($expectedState, $state)) {
                throw BspdxSignInException::failed('State missing or mismatched.');
            }

            $code = $request->query('code');

            if (! is_string($code) || $code === '' || ! is_string($verifier)) {
                throw BspdxSignInException::failed('Callback carried no code.');
            }

            $identity = $this->client->fetchIdentity($this->client->exchangeCode($code, $verifier));
            $user = $this->resolver->resolve($identity);
        } catch (BspdxSignInException $exception) {
            Log::warning('bspdx.com sign-in failed', ['reason' => $exception->getMessage()]);

            return redirect()->route('login')->withErrors(['bspdx' => $exception->userMessage()]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home', '/'));
    }
}
