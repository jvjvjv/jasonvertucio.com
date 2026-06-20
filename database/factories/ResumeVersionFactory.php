<?php

namespace Database\Factories;

use App\Models\ResumeVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResumeVersion>
 */
class ResumeVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version' => fake()->year().'.'.fake()->numberBetween(1, 5).'.'.fake()->numberBetween(0, 9),
            'is_current' => false,
            'docx_path' => null,
            'pdf_path' => null,
        ];
    }
}
