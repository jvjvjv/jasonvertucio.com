<?php

namespace Database\Factories;

use Jvjvjv\CodeTalker\Models\AiSystem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Jvjvjv\CodeTalker\Models\AiChatBot>
 */
class AiChatBotFactory extends Factory
{
    protected $model = \Jvjvjv\CodeTalker\Models\AiChatBot::class;

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
            'prompt_template' => 'You are {{bot_name}}. Respond helpfully to the visitor.',
            'allowed_roles' => [],
            'is_active' => true,
            'is_public' => true,
            'require_visitor_identity' => false,
        ];
    }
}
