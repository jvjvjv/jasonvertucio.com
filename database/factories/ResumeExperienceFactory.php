<?php

namespace Database\Factories;

use App\Models\ResumeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ResumeExperience>
 */
class ResumeExperienceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version_id' => ResumeVersion::factory(),
            'job_title' => fake()->jobTitle(),
            'company' => fake()->company(),
            'location' => fake()->optional()->passthrough(fake()->city() . ', ' . fake()->stateAbbr()),
            'date_start' => fake()->optional()->year(),
            'date_end' => fake()->optional()->randomElement([fake()->year(), 'Present']),
            'sort_order' => 0,
        ];
    }
}
