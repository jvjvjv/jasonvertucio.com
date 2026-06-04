<?php

namespace Database\Factories;

use Jvjvjv\CodeTalker\Enums\AiConversationStatus;
use Jvjvjv\CodeTalker\Models\AiSystem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Jvjvjv\CodeTalker\Models\AiConversation>
 */
class AiConversationFactory extends Factory
{
    protected $model = \Jvjvjv\CodeTalker\Models\AiConversation::class;

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
            'ai_chat_bot_id' => null,
            'feature' => 'targeted-resume',
            'title' => fake()->sentence(3),
            'visitor_name' => null,
            'visitor_email' => null,
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
