<?php

namespace Database\Factories;

use App\Models\ResumeSkillCategory;
use App\Models\ResumeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumeSkillCategory>
 */
class ResumeSkillCategoryFactory extends Factory
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
            'group' => fake()->randomElement(['top', 'other']),
            'title' => fake()->word(),
            'sort_order' => 0,
        ];
    }
}
