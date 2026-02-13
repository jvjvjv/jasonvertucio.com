<?php

namespace Database\Factories;

use App\Models\ResumeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ResumeProject>
 */
class ResumeProjectFactory extends Factory
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
            'project_name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'sort_order' => 0,
        ];
    }
}
