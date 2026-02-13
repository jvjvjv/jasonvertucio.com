<?php

namespace Database\Factories;

use App\Models\ResumeProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ResumeProjectBullet>
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
