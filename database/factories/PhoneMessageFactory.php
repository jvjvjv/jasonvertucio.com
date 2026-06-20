<?php

namespace Database\Factories;

use App\Models\PhoneMessage;
use App\Models\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PhoneMessageFactory extends Factory
{
    protected $model = PhoneMessage::class;

    public function definition(): array
    {
        return [
            'message' => $this->faker->sentence(),
            'sid' => 'SM'.Str::random(32),
            'from_phone_id' => PhoneNumber::factory(),
            'to_phone_id' => PhoneNumber::factory(),
        ];
    }
}
