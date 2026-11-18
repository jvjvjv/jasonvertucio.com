<?php

namespace Database\Factories;

use App\Models\CommentEmailOptOut;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommentEmailOptOut>
 */
class CommentEmailOptOutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
        ];
    }
}
