<?php

namespace Database\Factories;

use App\Enums\SalaryPeriod;
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
            'salary_start_amount' => fake()->optional(0.3)->randomFloat(2, 15, 200),
            'salary_start_period' => fake()->optional(0.3)->randomElement(SalaryPeriod::cases()),
            'salary_end_amount' => fake()->optional(0.3)->randomFloat(2, 15, 250),
            'salary_end_period' => fake()->optional(0.3)->randomElement(SalaryPeriod::cases()),
            'is_freelance' => fake()->boolean(20),
            'sort_order' => 0,
        ];
    }

    public function freelance(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_freelance' => true,
        ]);
    }
}
