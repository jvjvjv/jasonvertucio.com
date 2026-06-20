<?php

namespace Database\Factories;

use App\Models\ResumeExperience;
use App\Models\ResumeExperienceBullet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumeExperienceBullet>
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
