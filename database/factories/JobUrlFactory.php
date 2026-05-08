<?php

namespace Database\Factories;

use App\Models\JobUrl;
use App\Models\JobUrlParser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobUrl>
 */
class JobUrlFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_url_parser_id' => JobUrlParser::factory(),
            'url' => fake()->url(),
            'contents' => json_encode([
                'job_title' => fake()->jobTitle(),
                'company_name' => fake()->company(),
                'job_location' => fake()->city() . ', ' . fake()->stateAbbr(),
                'job_description' => fake()->paragraph(5),
                'reasoning' => fake()->sentence(),
            ]),
        ];
    }
}
