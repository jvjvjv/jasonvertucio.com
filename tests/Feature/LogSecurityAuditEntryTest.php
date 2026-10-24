<?php

namespace Tests\Feature;

use App\Enums\SecurityMethodKind;
use App\Events\UserSecurityMethodRemoved;
use App\Listeners\LogSecurityAuditEntry;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LogSecurityAuditEntryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_handling_the_event_creates_an_audit_log_row(): void
    {
        $user = User::factory()->create();
        $removedAt = new DateTimeImmutable;

        $event = new UserSecurityMethodRemoved($user, SecurityMethodKind::Passkey, 0, $removedAt);

        (new LogSecurityAuditEntry)->handle($event);

        $this->assertDatabaseHas('security_audit_log', [
            'user_id' => $user->id,
            'kind' => SecurityMethodKind::Passkey->value,
        ]);
    }
}
