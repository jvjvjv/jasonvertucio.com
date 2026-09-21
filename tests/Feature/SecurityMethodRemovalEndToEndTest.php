<?php

namespace Tests\Feature;

use App\Enums\SecurityMethodKind;
use App\Mail\SecurityMethodRemovedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Spatie\LaravelPasskeys\Models\Passkey;
use Tests\TestCase;

class SecurityMethodRemovalEndToEndTest extends TestCase
{
    use DatabaseTransactions;

    public function test_removing_last_passkey_via_http_records_audit_entry_and_queues_alert(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $passkey = Passkey::factory()->create(['authenticatable_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson(route('passkeys.destroy', $passkey->id))
            ->assertOk();

        $this->assertDatabaseHas('security_audit_log', [
            'user_id' => $user->id,
            'kind' => SecurityMethodKind::Passkey->value,
        ]);

        Mail::assertQueued(
            SecurityMethodRemovedMail::class,
            fn (SecurityMethodRemovedMail $mail) => $mail->hasTo($user->email)
        );
    }
}
