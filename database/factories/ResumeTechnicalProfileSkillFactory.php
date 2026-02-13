<?php

namespace Database\Factories;

use App\Models\ResumeTechnicalProfileCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ResumeTechnicalProfileSkill>
 */
class ResumeTechnicalProfileSkillFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_category_id' => ResumeTechnicalProfileCategory::factory(),
            'skill' => fake()->word(),
            'years' => fake()->optional()->randomFloat(1, 0.5, 15.0),
            'description' => fake()->optional()->sentence(),
            'sort_order' => 0,
        ];
    }
}
