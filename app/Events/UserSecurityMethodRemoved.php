<?php

namespace App\Events;

use App\Enums\SecurityMethodKind;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Events\Dispatchable;

class UserSecurityMethodRemoved
{
    use Dispatchable;

    public function __construct(
        public readonly User $user,
        public readonly SecurityMethodKind $kind,
        public readonly int $remainingMethodsOfKind,
        public readonly DateTimeImmutable $removedAt,
    ) {}
}
