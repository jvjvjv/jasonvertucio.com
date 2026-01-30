<?php

namespace Database\Factories;

use App\Models\IpBan;
use Illuminate\Database\Eloquent\Factories\Factory;

class IpBanFactory extends Factory
{
    protected $model = IpBan::class;

    public function definition(): array
    {
        return [
            'ip' => $this->faker->ipv4(),
            'banned_method' => $this->faker->randomElement(['POST', 'GET', 'PUT', 'DELETE']),
            'banned_url' => $this->faker->url(),
            'banned_body' => $this->faker->sentence(),
        ];
    }

    public function wpLogin(): self
    {
        return $this->state(fn (array $attributes) => [
            'banned_method' => 'POST',
            'banned_url' => '/wp-login.php',
        ]);
    }
}
