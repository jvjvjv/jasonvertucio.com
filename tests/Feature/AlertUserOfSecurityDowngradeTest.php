<?php

namespace Tests\Feature;

use App\Enums\SecurityMethodKind;
use App\Events\UserSecurityMethodRemoved;
use App\Listeners\AlertUserOfSecurityDowngrade;
use App\Mail\SecurityMethodRemovedMail;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AlertUserOfSecurityDowngradeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_handling_the_event_queues_the_downgrade_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $event = new UserSecurityMethodRemoved($user, SecurityMethodKind::TwoFactor, 0, new DateTimeImmutable);

        (new AlertUserOfSecurityDowngrade)->handle($event);

        Mail::assertQueued(
            SecurityMethodRemovedMail::class,
            fn (SecurityMethodRemovedMail $mail) => $mail->hasTo($user->email)
                && $mail->kind === SecurityMethodKind::TwoFactor
        );
    }
}
