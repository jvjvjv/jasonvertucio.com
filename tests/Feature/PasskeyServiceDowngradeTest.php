<?php

namespace Tests\Feature;

use App\Enums\SecurityMethodKind;
use App\Events\UserSecurityMethodRemoved;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Spatie\LaravelPasskeys\Models\Passkey;
use Tests\TestCase;

class PasskeyServiceDowngradeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_removing_one_of_two_passkeys_does_not_dispatch(): void
    {
        $user = User::factory()->create();
        $passkeys = Passkey::factory()->count(2)->create(['authenticatable_id' => $user->id]);

        Event::fake([UserSecurityMethodRemoved::class]);

        $this->actingAs($user)
            ->deleteJson(route('passkeys.destroy', $passkeys->first()->id))
            ->assertOk();

        Event::assertNotDispatched(UserSecurityMethodRemoved::class);
        $this->assertSame(1, $user->passkeys()->count());
    }

    public function test_removing_the_last_passkey_dispatches_downgrade_event(): void
    {
        $user = User::factory()->create();
        $passkey = Passkey::factory()->create(['authenticatable_id' => $user->id]);

        Event::fake([UserSecurityMethodRemoved::class]);

        $this->actingAs($user)
            ->deleteJson(route('passkeys.destroy', $passkey->id))
            ->assertOk();

        Event::assertDispatched(
            UserSecurityMethodRemoved::class,
            fn (UserSecurityMethodRemoved $event) => $event->user->is($user)
                && $event->kind === SecurityMethodKind::Passkey
                && $event->remainingMethodsOfKind === 0
        );
        $this->assertSame(0, $user->passkeys()->count());
    }
}
