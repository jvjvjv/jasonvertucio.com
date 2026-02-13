<?php

namespace Database\Factories;

use App\Models\ResumeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ResumeTechnicalProfileCategory>
 */
class ResumeTechnicalProfileCategoryFactory extends Factory
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
            'category' => fake()->word(),
            'is_main' => false,
            'sort_order' => 0,
        ];
    }
}
