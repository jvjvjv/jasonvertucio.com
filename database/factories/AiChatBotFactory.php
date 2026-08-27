<?php

namespace Database\Factories;

use App\Models\AiChatBot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * @extends Factory<AiChatBot>
 */
class AiChatBotFactory extends Factory
{
    protected $model = AiChatBot::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_system_id' => AiSystem::factory(),
            'context_length' => null,
            'temperature' => null,
            'name' => fake()->words(3, true),
            'slug' => fake()->unique()->slug(),
            'access_path' => 'chat',
            'description' => fake()->sentence(),
            'prompt_template' => 'You are {{persona_name}}. Respond helpfully to the visitor.',
            'allowed_roles' => [],
            'is_active' => true,
            'require_visitor_identity' => false,
        ];
    }
}
