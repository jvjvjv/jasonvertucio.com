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
            // Mirrors the shape a real snapshot is seeded with — every key
            // `ResumeDataServiceContract::getAllEditableData()` returns, and at
            // least one entry per list section. A thinner default hides view
            // regressions on the fields it happens to omit.
            'snapshot' => [
                'personal' => [
                    'name' => fake()->name(),
                    'title' => fake()->jobTitle(),
                    'email' => fake()->safeEmail(),
                    'phone' => fake()->phoneNumber(),
                    'linkedin' => 'linkedin.com/in/'.fake()->userName(),
                    'url' => fake()->url(),
                    'summary' => fake()->paragraph(),
                ],
                'skills' => [
                    'top' => [['title' => 'Front-End', 'list' => ['Vue', 'React']]],
                    'other' => [['title' => 'Design Tools', 'list' => ['Figma']]],
                ],
                'experience' => [[
                    'jobTitle' => fake()->jobTitle(),
                    'company' => fake()->company(),
                    'location' => fake()->city(),
                    'dates' => ['2024-01-01', '2026-01-01'],
                    'bullets' => [fake()->sentence()],
                ]],
                'education' => [[
                    'institution' => fake()->company().' University',
                    'degree' => 'Computer Science',
                    'level' => 'No Degree',
                    'dates' => ['', ''],
                ]],
                'projects' => [[
                    'projectName' => fake()->words(2, true),
                    'description' => fake()->sentence(),
                    'bullets' => [fake()->sentence()],
                ]],
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
