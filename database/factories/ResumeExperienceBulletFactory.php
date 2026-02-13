<?php

namespace Database\Factories;

use App\Models\ResumeExperience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ResumeExperienceBullet>
 */
class ResumeExperienceBulletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'experience_id' => ResumeExperience::factory(),
            'content' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
