<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jvjvjv\CodeTalker\Models\AiFeatureMemory;

/**
 * @extends Factory<AiFeatureMemory>
 */
class AiFeatureMemoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feature' => 'targeted-resume',
            'category' => fake()->randomElement(['preference', 'domain_knowledge', 'system_tuning']),
            'key' => fake()->unique()->slug(3),
            'content' => fake()->sentence(),
            'confidence' => fake()->numberBetween(30, 95),
            'is_active' => true,
            'times_reinforced' => fake()->numberBetween(0, 5),
        ];
    }

    public function preference(): static
    {
        return $this->state(fn () => ['category' => 'preference']);
    }

    public function domainKnowledge(): static
    {
        return $this->state(fn () => ['category' => 'domain_knowledge']);
    }

    public function systemTuning(): static
    {
        return $this->state(fn () => ['category' => 'system_tuning']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
