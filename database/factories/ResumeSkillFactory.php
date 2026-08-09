<?php

namespace Database\Factories;

use App\Models\ResumeSkill;
use App\Models\ResumeSkillCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumeSkill>
 */
class ResumeSkillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => ResumeSkillCategory::factory(),
            'name' => fake()->word(),
            'sort_order' => 0,
        ];
    }
}
