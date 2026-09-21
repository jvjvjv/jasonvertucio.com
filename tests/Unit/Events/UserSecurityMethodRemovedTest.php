<?php

namespace Tests\Unit\Events;

use App\Enums\SecurityMethodKind;
use App\Events\UserSecurityMethodRemoved;
use App\Models\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class UserSecurityMethodRemovedTest extends TestCase
{
    public function test_exposes_constructed_properties(): void
    {
        $user = new User(['name' => 'Test User']);
        $removedAt = new DateTimeImmutable;

        $event = new UserSecurityMethodRemoved($user, SecurityMethodKind::Passkey, 0, $removedAt);

        $this->assertSame($user, $event->user);
        $this->assertSame(SecurityMethodKind::Passkey, $event->kind);
        $this->assertSame(0, $event->remainingMethodsOfKind);
        $this->assertSame($removedAt, $event->removedAt);
    }
}
