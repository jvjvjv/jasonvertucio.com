<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jvjvjv\CodeTalker\Enums\AiProvider;
use Jvjvjv\CodeTalker\Enums\AiSystemRole;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * @extends Factory<AiSystem>
 */
class AiSystemFactory extends Factory
{
    protected $model = AiSystem::class;

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
            'allowed_tools' => null,
        ];
    }

    /**
     * A text-to-speech system on a local OpenAI-compatible speech server.
     */
    public function audio(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => AiProvider::OpenAI->value,
            'role' => AiSystemRole::Audio,
            'model' => 'kokoro',
            'base_url' => 'http://10.10.0.2:8000/v1',
            'config' => ['voice' => 'af_heart'],
        ]);
    }

    /**
     * A speech-to-text system on a local OpenAI-compatible speech server.
     */
    public function transcription(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => AiProvider::OpenAI->value,
            'role' => AiSystemRole::Transcription,
            'model' => 'Systran/faster-whisper-medium',
            'base_url' => 'http://10.10.0.2:8000/v1',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
