<?php

namespace Tests\Feature\Auth;

use App\Models\FederatedIdentity;
use App\Models\User;
use App\Services\Auth\BspdxOAuthClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class BspdxLoginTest extends TestCase
{
    use DatabaseTransactions;

    private const BASE = 'https://bspdx.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.bspdx' => [
                'enabled' => true,
                'base_url' => self::BASE,
                'client_id' => 'client-123',
                'client_secret' => 'secret-xyz',
                'redirect' => null,
                'scopes' => 'userinfo',
                'timeout' => 5,
            ],
        ]);

        RateLimiter::clear('federated-login:127.0.0.1');
    }

    /**
     * Start the flow and return the state bspdx.com would echo back.
     */
    private function startFlow(): string
    {
        $location = $this->get(route('auth.bspdx.redirect'))->assertRedirect()->headers->get('Location');

        $this->assertStringStartsWith(self::BASE.'/oauth/authorize?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('client-123', $query['client_id']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame('userinfo', $query['scope']);
        $this->assertSame(route('auth.bspdx.callback'), $query['redirect_uri']);

        return $query['state'];
    }

    /**
     * @param  array<string, mixed>  $userinfo
     */
    private function fakeProvider(array $userinfo, int $userinfoStatus = 200): void
    {
        Http::fake([
            self::BASE.'/oauth/token' => Http::response(['access_token' => 'access-abc', 'token_type' => 'Bearer']),
            self::BASE.'/api/oauth/userinfo' => Http::response($userinfo, $userinfoStatus),
        ]);
    }

    private function returnFromBspdx(string $state, string $code = 'auth-code'): TestResponse
    {
        return $this->get(route('auth.bspdx.callback', ['state' => $state, 'code' => $code]));
    }

    public function test_a_new_identity_creates_a_user_with_no_roles_and_signs_in(): void
    {
        $subject = (string) Str::uuid();
        $email = 'newperson-'.Str::random(6).'@example.com';
        $this->fakeProvider(['id' => $subject, 'email' => $email, 'email_verified' => false, 'name' => $email]);

        $this->returnFromBspdx($this->startFlow())->assertRedirect('/');

        $user = User::where('email', $email)->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(strstr($email, '@', true), $user->name);
        $this->assertCount(0, $user->roles);
        $this->assertDatabaseHas('federated_identities', ['user_id' => $user->id, 'provider' => 'bspdx', 'subject' => $subject]);
    }

    public function test_the_code_exchange_sends_the_pkce_verifier_and_the_token_is_used_for_userinfo(): void
    {
        $email = 'pkce-'.Str::random(6).'@example.com';
        $this->fakeProvider(['id' => (string) Str::uuid(), 'email' => $email]);

        $location = $this->get(route('auth.bspdx.redirect'))->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->returnFromBspdx($query['state'])->assertRedirect('/');

        Http::assertSent(function (Request $request) use ($query): bool {
            return $request->url() === self::BASE.'/oauth/token'
                && BspdxOAuthClient::codeChallenge($request['code_verifier']) === $query['code_challenge']
                && $request['client_secret'] === 'secret-xyz'
                && $request['code'] === 'auth-code';
        });
        Http::assertSent(fn (Request $request): bool => $request->url() === self::BASE.'/api/oauth/userinfo'
            && $request->hasHeader('Authorization', 'Bearer access-abc'));
    }

    public function test_a_new_user_cannot_reach_admin_pages(): void
    {
        $this->fakeProvider(['id' => (string) Str::uuid(), 'email' => 'noadmin-'.Str::random(6).'@example.com']);

        $this->returnFromBspdx($this->startFlow());

        $this->get('/admin')->assertForbidden();
    }

    public function test_a_returning_identity_signs_in_as_the_same_user_and_follows_an_email_change(): void
    {
        $identity = FederatedIdentity::factory()->create();
        $newEmail = 'changed-'.Str::random(6).'@example.com';
        $this->fakeProvider(['id' => $identity->subject, 'email' => $newEmail]);

        $this->returnFromBspdx($this->startFlow())->assertRedirect('/');

        $this->assertAuthenticatedAs($identity->user);
        $this->assertSame($newEmail, $identity->user->fresh()->email);
        $this->assertNull($identity->user->fresh()->email_verified_at);
        $this->assertSame(1, FederatedIdentity::where('subject', $identity->subject)->count());
    }

    public function test_an_email_change_onto_another_users_address_is_not_applied(): void
    {
        $identity = FederatedIdentity::factory()->create();
        $originalEmail = $identity->user->email;
        $other = User::factory()->create();
        $this->fakeProvider(['id' => $identity->subject, 'email' => $other->email]);

        $this->returnFromBspdx($this->startFlow())->assertRedirect('/');

        $this->assertAuthenticatedAs($identity->user);
        $this->assertSame($originalEmail, $identity->user->fresh()->email);
        $this->assertSame($other->email, $other->fresh()->email);
    }

    public function test_an_unlinked_identity_with_an_existing_local_email_is_refused(): void
    {
        $existing = User::factory()->create();
        $before = User::count();
        $this->fakeProvider(['id' => (string) Str::uuid(), 'email' => strtoupper($existing->email), 'email_verified' => true]);

        $this->returnFromBspdx($this->startFlow())
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['bspdx' => 'An account with this email already exists here. Contact the site owner to have it connected to your bspdx.com account.']);

        $this->assertGuest();
        $this->assertSame($before, User::count());
        $this->assertSame(0, $existing->federatedIdentities()->count());
    }

    public function test_a_mismatched_state_is_refused_without_calling_the_provider(): void
    {
        Http::fake();
        $this->startFlow();

        $this->returnFromBspdx('not-the-state')->assertRedirect(route('login'))->assertSessionHasErrors('bspdx');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_a_callback_without_a_started_flow_is_refused(): void
    {
        Http::fake();

        $this->returnFromBspdx('anything')->assertRedirect(route('login'))->assertSessionHasErrors('bspdx');

        Http::assertNothingSent();
    }

    public function test_a_state_cannot_be_replayed(): void
    {
        $this->fakeProvider(['id' => (string) Str::uuid(), 'email' => 'replay-'.Str::random(6).'@example.com']);
        $state = $this->startFlow();

        $this->returnFromBspdx($state)->assertRedirect('/');
        auth()->logout();

        $this->returnFromBspdx($state)->assertRedirect(route('login'))->assertSessionHasErrors('bspdx');
    }

    public function test_denied_consent_is_refused(): void
    {
        Http::fake();
        $state = $this->startFlow();

        $this->get(route('auth.bspdx.callback', ['state' => $state, 'error' => 'access_denied']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('bspdx');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_a_rejected_code_or_verifier_is_refused(): void
    {
        Http::fake([self::BASE.'/oauth/token' => Http::response(['error' => 'invalid_grant'], 400)]);

        $this->returnFromBspdx($this->startFlow())->assertRedirect(route('login'))->assertSessionHasErrors('bspdx');

        $this->assertGuest();
    }

    public function test_a_userinfo_error_is_refused(): void
    {
        $this->fakeProvider(['error' => 'Unauthenticated'], 401);

        $this->returnFromBspdx($this->startFlow())->assertRedirect(route('login'))->assertSessionHasErrors('bspdx');

        $this->assertGuest();
    }

    public function test_userinfo_without_an_id_or_email_is_refused(): void
    {
        foreach ([['email' => 'x-'.Str::random(6).'@example.com'], ['id' => (string) Str::uuid()]] as $body) {
            $this->fakeProvider($body);
            $before = User::count();

            $this->returnFromBspdx($this->startFlow())->assertRedirect(route('login'))->assertSessionHasErrors('bspdx');

            $this->assertGuest();
            $this->assertSame($before, User::count());
        }
    }

    public function test_a_redirect_from_the_provider_is_not_followed(): void
    {
        Http::fake([
            self::BASE.'/oauth/token' => Http::response('', 302, ['Location' => 'https://evil.test/token']),
            'https://evil.test/*' => Http::response(['access_token' => 'stolen']),
        ]);

        $this->returnFromBspdx($this->startFlow())->assertRedirect(route('login'))->assertSessionHasErrors('bspdx');

        Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://evil.test'));
        $this->assertGuest();
    }

    public function test_an_unreachable_provider_is_refused(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->returnFromBspdx($this->startFlow())->assertRedirect(route('login'))->assertSessionHasErrors('bspdx');

        $this->assertGuest();
    }

    public function test_the_flow_is_unavailable_when_disabled_or_misconfigured(): void
    {
        foreach ([
            ['services.bspdx.enabled' => false],
            ['services.bspdx.client_secret' => null],
            ['services.bspdx.base_url' => 'http://bspdx.test'],
        ] as $override) {
            config($override);

            $this->get(route('auth.bspdx.redirect'))->assertNotFound();
            $this->get(route('auth.bspdx.callback'))->assertNotFound();
            $this->get(route('login'))->assertOk()
                ->assertDontSee('Continue to bspdx.com')
                ->assertSee('Signing in is turned off right now.');

            config(['services.bspdx.enabled' => true, 'services.bspdx.client_secret' => 'secret-xyz', 'services.bspdx.base_url' => self::BASE]);
        }

        $this->get(route('login'))->assertOk()->assertSee('Continue to bspdx.com');
    }

    public function test_sign_in_attempts_are_throttled_per_address(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->get(route('auth.bspdx.redirect'))->assertRedirect();
        }

        $this->get(route('auth.bspdx.redirect'))->assertStatus(429);
    }
}
