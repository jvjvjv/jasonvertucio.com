<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\LaravelPasskeys\Models\Passkey;
use Tests\TestCase;

class PasskeyRemovalNonBlockingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_response_is_unchanged_whether_or_not_downgrade_processing_fires(): void
    {
        $userWithOtherPasskeyRemaining = User::factory()->create();
        $passkeys = Passkey::factory()->count(2)->create([
            'authenticatable_id' => $userWithOtherPasskeyRemaining->id,
        ]);

        $noDowngradeResponse = $this->actingAs($userWithOtherPasskeyRemaining)
            ->deleteJson(route('passkeys.destroy', $passkeys->first()->id));

        $userLosingLastPasskey = User::factory()->create();
        $lastPasskey = Passkey::factory()->create([
            'authenticatable_id' => $userLosingLastPasskey->id,
        ]);

        $downgradeResponse = $this->actingAs($userLosingLastPasskey)
            ->deleteJson(route('passkeys.destroy', $lastPasskey->id));

        $this->assertSame($noDowngradeResponse->getStatusCode(), $downgradeResponse->getStatusCode());
        $this->assertSame($noDowngradeResponse->json(), $downgradeResponse->json());
        $downgradeResponse->assertOk();
    }
}
