<?php

namespace Database\Factories;

use App\Models\ResumePersonalInfo;
use App\Models\ResumeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumePersonalInfo>
 */
class ResumePersonalInfoFactory extends Factory
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
            'name' => fake()->name(),
            'title' => fake()->jobTitle(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'linkedin' => fake()->optional()->url(),
            'summary' => fake()->optional()->paragraph(),
        ];
    }
}
