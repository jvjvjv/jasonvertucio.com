<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiSystem>
 */
class AiSystemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'provider' => 'anthropic',
            'api_key' => fake()->sha256(),
            'model' => 'claude-sonnet-4-6',
            'max_tokens' => 4096,
            'context_length' => null,
            'temperature' => 0.7,
            'is_active' => true,
            'config' => [],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
