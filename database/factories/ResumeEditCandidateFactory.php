<?php

namespace Database\Factories;

use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumeEditCandidate>
 */
class ResumeEditCandidateFactory extends Factory
{
    protected $model = ResumeEditCandidate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = now();

        return [
            'base_resume_version_id' => ResumeVersion::factory(),
            'revision_number' => 1,
            'status' => 'pending',
            'snapshot' => [
                'personal' => [
                    'name' => fake()->name(),
                    'title' => fake()->jobTitle(),
                    'email' => fake()->safeEmail(),
                ],
                'skills' => ['top' => [], 'other' => []],
                'experience' => [],
                'education' => [],
                'projects' => [],
            ],
            'ai_conversation_id' => null,
            'batch_started_at' => $now,
            'last_edited_at' => $now,
            'approved_at' => null,
            'approved_by_user_id' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }
}
