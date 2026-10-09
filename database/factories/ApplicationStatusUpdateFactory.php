<?php

namespace Database\Factories;

use App\Enums\TargetedResumeApplicationStatus;
use App\Models\TargetedResume;
use App\Models\TargetedResumeStatusUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TargetedResumeStatusUpdate>
 */
class TargetedResumeStatusUpdateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'targeted_resume_id' => TargetedResume::factory(),
            'status' => TargetedResumeApplicationStatus::Applied,
            'notes' => fake()->optional()->sentence(),
            'occurred_at' => now(),
        ];
    }

    public function status(TargetedResumeApplicationStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
