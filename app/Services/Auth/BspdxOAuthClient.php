<?php

namespace App\Services\Auth;

use App\Exceptions\BspdxSignInException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Talks to bspdx.com's Passport server for the authorization-code flow.
 *
 * bspdx.com is plain OAuth 2.0, not OpenID Connect: there is no signed ID
 * token to verify. The identity is trusted because it comes from userinfo,
 * called server-to-server over HTTPS with an access token this site obtained
 * itself in the code exchange (bound to the browser by `state` and PKCE).
 * Neither call follows redirects, so a redirect cannot steer the token or the
 * identity lookup to another host.
 */
class BspdxOAuthClient
{
    /**
     * Whether federated sign-in is switched on and fully configured.
     */
    public function isAvailable(): bool
    {
        return (bool) config('services.bspdx.enabled') && $this->isConfigured();
    }

    /**
     * Whether the base address is HTTPS and client credentials are present.
     */
    public function isConfigured(): bool
    {
        return str_starts_with((string) config('services.bspdx.base_url'), 'https://')
            && filled(config('services.bspdx.client_id'))
            && filled(config('services.bspdx.client_secret'));
    }

    /**
     * The address to send the browser to.
     */
    public function authorizationUrl(string $state, string $codeVerifier): string
    {
        $query = array_filter([
            'response_type' => 'code',
            'client_id' => config('services.bspdx.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => trim((string) config('services.bspdx.scopes')),
            'state' => $state,
            'code_challenge' => self::codeChallenge($codeVerifier),
            'code_challenge_method' => 'S256',
        ], fn (mixed $value): bool => $value !== '' && $value !== null);

        return $this->endpoint('/oauth/authorize').'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchange an authorization code for an access token.
     */
    public function exchangeCode(string $code, string $codeVerifier): string
    {
        $response = $this->send(fn (PendingRequest $request): Response => $request->asForm()->post($this->endpoint('/oauth/token'), [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.bspdx.client_id'),
            'client_secret' => config('services.bspdx.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ]), 'token');

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw BspdxSignInException::failed('Token response carried no access_token.');
        }

        return $token;
    }

    /**
     * Read the signed-in person's identity with an access token from
     * {@see exchangeCode()}.
     */
    public function fetchIdentity(string $accessToken): BspdxIdentity
    {
        $response = $this->send(
            fn (PendingRequest $request): Response => $request->withToken($accessToken)->get($this->endpoint('/api/oauth/userinfo')),
            'userinfo',
        );

        $subject = $response->json('id');
        $email = $response->json('email');
        $name = $response->json('name');

        if ((! is_string($subject) && ! is_int($subject)) || (string) $subject === '') {
            throw BspdxSignInException::failed('Userinfo response carried no id.');
        }

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw BspdxSignInException::failed('Userinfo response carried no usable email.');
        }

        return new BspdxIdentity((string) $subject, $email, is_string($name) ? $name : null);
    }

    /**
     * The PKCE S256 challenge for a verifier.
     */
    public static function codeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    /**
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call, string $step): Response
    {
        $timeout = max(1, (int) config('services.bspdx.timeout', 10));

        try {
            $response = $call(Http::acceptJson()->timeout($timeout)->connectTimeout($timeout)->withoutRedirecting());
        } catch (ConnectionException $exception) {
            throw BspdxSignInException::failed("bspdx.com {$step} call failed: {$exception->getMessage()}");
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw BspdxSignInException::failed("bspdx.com {$step} call returned HTTP {$response->status()}.");
        }

        return $response;
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.bspdx.base_url'), '/').$path;
    }

    private function redirectUri(): string
    {
        return filled(config('services.bspdx.redirect'))
            ? (string) config('services.bspdx.redirect')
            : route('auth.bspdx.callback');
    }
}
