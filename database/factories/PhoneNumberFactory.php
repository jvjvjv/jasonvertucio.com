<?php

namespace Database\Factories;

use App\Models\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

class PhoneNumberFactory extends Factory
{
    protected $model = PhoneNumber::class;

    public function definition(): array
    {
        return [
            'phone_number' => $this->faker->phoneNumber(),
            'active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
