<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\AiConversation;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    /**
     * Defaults to a draft main-resume application: no targeted resume and no
     * AI session.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'resume_version_id' => ResumeVersion::factory(),
            'targeted_resume_id' => null,
            'ai_conversation_id' => null,
            'job_url_id' => null,
            'company_name' => fake()->company(),
            'position' => fake()->jobTitle(),
            'location' => fake()->optional()->city(),
            'job_description' => fake()->paragraphs(3, true),
            'fit_score' => fake()->optional()->numberBetween(1, 100),
            'fit_summary' => fake()->optional()->sentence(),
            'status' => ApplicationStatus::Draft,
        ];
    }

    /**
     * An application that has been applied to, with the `applied`
     * status-history entry that makes it count.
     */
    public function applied(?CarbonInterface $occurredAt = null): static
    {
        return $this
            ->state(fn (array $attributes): array => ['status' => ApplicationStatus::Applied])
            ->afterCreating(function (Application $application) use ($occurredAt): void {
                ApplicationStatusUpdate::factory()->create([
                    'application_id' => $application->id,
                    'status' => ApplicationStatus::Applied,
                    'occurred_at' => $occurredAt ?? now(),
                ]);
            });
    }

    public function passed(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ApplicationStatus::Passed]);
    }

    /**
     * Attaches a targeted resume tailored from the application's own resume
     * version.
     */
    public function withTargetedResume(): static
    {
        return $this->state([
            'targeted_resume_id' => fn (array $attributes): TargetedResumeFactory => TargetedResume::factory()->state([
                'resume_version_id' => $attributes['resume_version_id'],
            ]),
        ]);
    }

    public function withConversation(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ai_conversation_id' => AiConversation::factory()->state(['feature' => 'targeted-resume']),
        ]);
    }
}
