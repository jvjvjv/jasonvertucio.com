<?php

namespace Database\Factories;

use App\Models\ResumeShareCode;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResumeShareCodeFactory extends Factory
{
    protected $model = ResumeShareCode::class;

    public function definition(): array
    {
        return [
            'id' => ResumeShareCode::generateCode(),
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'expires_at' => null,
            'email_sent' => false,
            'notify_on_update' => false,
        ];
    }

    public function withEmail(): self
    {
        return $this->state(fn (array $attributes) => [
            'email' => $this->faker->safeEmail(),
            'email_sent' => true,
        ]);
    }

    public function withExpiration(): self
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function notifyOnUpdate(): self
    {
        return $this->state(fn (array $attributes) => [
            'notify_on_update' => true,
            'email' => $this->faker->safeEmail(),
        ]);
    }
}
