<?php

namespace Tests\Feature\Auth;

use App\Models\FederatedIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * bspdx.com is the only way to sign in: every local credential path is gone,
 * and the login and profile pages say so.
 */
class BspdxOnlySignInTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.bspdx.enabled' => true,
            'services.bspdx.base_url' => 'https://bspdx.test',
            'services.bspdx.client_id' => 'client-123',
            'services.bspdx.client_secret' => 'secret-xyz',
        ]);
    }

    public function test_a_password_sign_in_creates_no_session(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(405);

        $this->assertGuest();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function removedEndpoints(): array
    {
        return [
            'passkey login options' => ['post', '/passkey/login/options'],
            'passkey sign-in' => ['post', '/passkey/authenticate'],
            'authenticator-code sign-in' => ['post', '/login/totp'],
            'login method check' => ['post', '/login/check-email'],
            'registration form' => ['get', '/register'],
            'registration' => ['post', '/register'],
            'forgot password' => ['post', '/forgot-password'],
            'reset password' => ['post', '/reset-password'],
            'two-factor challenge' => ['post', '/two-factor-challenge'],
            'confirm password' => ['post', '/user/confirm-password'],
        ];
    }

    #[DataProvider('removedEndpoints')]
    public function test_removed_sign_in_endpoints_do_not_authenticate(string $method, string $uri): void
    {
        $user = User::factory()->create();

        $response = $this->{$method}($uri, ['email' => $user->email, 'password' => 'password', 'totp_code' => '123456']);

        $this->assertContains($response->getStatusCode(), [404, 405]);
        $this->assertGuest();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function removedAccountEndpoints(): array
    {
        return [
            'password change' => ['put', '/user/password'],
            'two-factor enable' => ['post', '/user/two-factor-authentication'],
            'passkey registration' => ['post', '/user/passkeys'],
            'login preferences' => ['put', '/profile/auth-preferences'],
        ];
    }

    #[DataProvider('removedAccountEndpoints')]
    public function test_removed_account_security_endpoints_are_gone(string $method, string $uri): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertContains($this->{$method}($uri)->getStatusCode(), [404, 405]);
    }

    public function test_the_login_page_offers_only_bspdx(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Continue to bspdx.com')
            ->assertSee(route('auth.bspdx.redirect'), false)
            ->assertDontSee('type="password"', false)
            ->assertDontSee('type="email"', false)
            ->assertDontSee('passkey', false);
    }

    public function test_a_guest_on_a_protected_page_is_sent_to_the_login_page(): void
    {
        $this->get(route('profile.show'))->assertRedirect(route('login'));
    }

    public function test_the_profile_shows_the_linked_identity_and_no_security_controls(): void
    {
        $identity = FederatedIdentity::factory()->create();

        $this->actingAs($identity->user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Signs in with')
            ->assertSee($identity->created_at->format('M j, Y'))
            ->assertSee('Manage your bspdx.com account')
            ->assertSee('https://bspdx.test/profile', false)
            ->assertDontSee('Change Password')
            ->assertDontSee('Two-Factor Authentication')
            ->assertDontSee('Passkeys')
            ->assertDontSee('Login Preferences');
    }

    public function test_the_profile_warns_an_unlinked_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('This account is not connected to bspdx.com yet');
    }

    public function test_logout_still_works(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }
}
