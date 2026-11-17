<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationContact>
 */
class ApplicationContactFactory extends Factory
{
    protected $model = ApplicationContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'role' => fake()->randomElement(['Recruiter', 'Hiring Manager', 'Talent Partner']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
