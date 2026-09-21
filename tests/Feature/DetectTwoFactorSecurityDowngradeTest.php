<?php

namespace Tests\Feature;

use App\Enums\SecurityMethodKind;
use App\Events\UserSecurityMethodRemoved;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class DetectTwoFactorSecurityDowngradeTest extends TestCase
{
    use DatabaseTransactions;

    private function withConfirmedPassword(User $user): self
    {
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        return $this;
    }

    public function test_disabling_confirmed_two_factor_dispatches_downgrade_event(): void
    {
        $user = User::factory()->create();
        $this->withConfirmedPassword($user);

        $this->post(route('two-factor.enable'))->assertSessionHasNoErrors();

        $user->refresh();
        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);
        $code = app(Google2FA::class)->getCurrentOtp($secret);

        $this->post(route('two-factor.confirm'), ['code' => $code])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);

        Event::fake([UserSecurityMethodRemoved::class]);

        $this->delete(route('two-factor.disable'));

        Event::assertDispatched(
            UserSecurityMethodRemoved::class,
            fn (UserSecurityMethodRemoved $event) => $event->user->is($user)
                && $event->kind === SecurityMethodKind::TwoFactor
                && $event->remainingMethodsOfKind === 0
        );
    }

    public function test_disabling_never_confirmed_two_factor_does_not_dispatch(): void
    {
        $user = User::factory()->create();
        $this->withConfirmedPassword($user);

        $this->post(route('two-factor.enable'))->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNull($user->two_factor_confirmed_at);

        Event::fake([UserSecurityMethodRemoved::class]);

        $this->delete(route('two-factor.disable'));

        Event::assertNotDispatched(UserSecurityMethodRemoved::class);
    }
}
