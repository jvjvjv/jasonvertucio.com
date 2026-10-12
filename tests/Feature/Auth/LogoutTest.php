<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_get_request_does_not_end_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/logout')->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_post_ends_the_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_a_post_without_a_forgery_token_is_refused(): void
    {
        $user = User::factory()->create();

        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->actingAs($user)
            ->post('/logout')
            ->assertStatus(419);

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_signed_in_homepage_offers_a_logout_form_and_no_logout_link(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('href="/logout"', $html);
        $this->assertMatchesRegularExpression('#<form[^>]*method="POST"[^>]*action="[^"]*/logout"#i', $html);
        $this->assertStringContainsString('name="_token"', $html);
    }

    public function test_the_top_navbar_offers_a_logout_form_to_signed_in_users_only(): void
    {
        $anonymous = $this->get('/blog')->assertOk()->getContent();
        $this->assertStringNotContainsString('/logout', $anonymous);

        $this->actingAs(User::factory()->create());
        $html = $this->get('/blog')->assertOk()->getContent();

        $this->assertStringNotContainsString('href="/logout"', $html);
        $this->assertMatchesRegularExpression('#<form[^>]*method="POST"[^>]*action="[^"]*/logout"#i', $html);
    }

    public function test_an_anonymous_visitor_sees_no_logout_control(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('/logout', $html);
    }
}
