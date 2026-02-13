<?php

namespace Database\Factories;

use App\Models\ResumeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ResumeEducation>
 */
class ResumeEducationFactory extends Factory
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
            'institution' => fake()->company(),
            'degree' => fake()->optional()->word(),
            'date_start' => fake()->optional()->year(),
            'date_end' => fake()->optional()->year(),
            'description' => fake()->optional()->sentence(),
            'sort_order' => 0,
        ];
    }
}
