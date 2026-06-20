<?php

namespace Database\Factories;

use App\Enums\TargetedResumeStatus;
use App\Models\AiConversation;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TargetedResume>
 */
class TargetedResumeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resume_version_id' => ResumeVersion::factory(),
            'ai_conversation_id' => AiConversation::factory(),
            'company_name' => fake()->company(),
            'position' => fake()->jobTitle(),
            'title' => fake()->jobTitle(),
            'job_description' => fake()->paragraphs(3, true),
            'tailored_data' => [],
            'fit_score' => fake()->optional()->numberBetween(1, 100),
            'fit_summary' => fake()->optional()->sentence(),
            'status' => TargetedResumeStatus::Draft,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TargetedResumeStatus::Draft,
        ]);
    }

    public function finalized(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TargetedResumeStatus::Finalized,
        ]);
    }

    public function applied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TargetedResumeStatus::Applied,
        ]);
    }
}
