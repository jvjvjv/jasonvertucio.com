<?php

namespace Database\Factories;

use App\Models\ResumeProject;
use App\Models\ResumeProjectBullet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumeProjectBullet>
 */
class ResumeProjectBulletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => ResumeProject::factory(),
            'content' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
