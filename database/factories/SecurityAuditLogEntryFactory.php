<?php

namespace Database\Factories;

use App\Enums\SecurityMethodKind;
use App\Models\SecurityAuditLogEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SecurityAuditLogEntryFactory extends Factory
{
    protected $model = SecurityAuditLogEntry::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'kind' => $this->faker->randomElement(SecurityMethodKind::cases()),
            'removed_at' => now(),
        ];
    }
}
