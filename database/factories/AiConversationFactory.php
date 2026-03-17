<?php

namespace Database\Factories;

use App\Enums\AiConversationStatus;
use App\Models\AiSystem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiConversation>
 */
class AiConversationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ai_system_id' => AiSystem::factory(),
            'feature' => 'targeted-resume',
            'title' => fake()->sentence(3),
            'status' => AiConversationStatus::Active,
            'context' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AiConversationStatus::Active,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AiConversationStatus::Completed,
        ]);
    }

    public function pass(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AiConversationStatus::Pass,
        ]);
    }
}
