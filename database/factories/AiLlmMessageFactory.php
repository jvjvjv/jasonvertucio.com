<?php

namespace Database\Factories;

use Jvjvjv\CodeTalker\Models\AiConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiLlmMessage>
 */
class AiLlmMessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = \Jvjvjv\CodeTalker\Models\AiLlmMessage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ai_conversation_id' => AiConversation::factory(),
            'direction' => fake()->randomElement(['request', 'response']),
            'turn_number' => fake->numberBetween(1, 10),
            'request_data' => [
                'model' => 'claude-3-haiku-20240307',
                'messages' => [
                    ['role' => 'user', 'content' => 'Hello'],
                ],
                'max_tokens' => 1000,
            ],
            'response_data' => [
                'model' => 'claude-3-haiku-20240307',
                'choices' => [
                    ['message' => ['role' => 'assistant', 'content' => 'Hi there!']],
                ],
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 5,
                    'total_tokens' => 15,
                ],
            ],
            'duration_ms' => fake()->numberBetween(500, 3000),
            'created_at' => now(),
        ];
    }

    /**
     * Indicate that the message is a request.
     */
    public function request(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'request',
        ]);
    }

    /**
     * Indicate that the message is a response.
     */
    public function response(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => 'response',
        ]);
    }

    /**
     * Set a specific turn number.
     */
    public function turn(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'turn_number' => (string) $number,
        ]);
    }
}