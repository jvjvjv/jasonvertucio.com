<?php

namespace Database\Factories;

use App\Models\JobUrlParser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobUrlParser>
 */
class JobUrlParserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain' => fake()->domainName(),
            'company_name_selector' => null,
            'job_title_selector' => null,
            'job_description_selector' => null,
            'html' => null,
            'ai_reasoning' => null,
            'status' => 'inactive',
        ];
    }

    /**
     * Set the parser as active.
     */
    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    /**
     * Set CSS selectors for all fields.
     */
    public function withSelectors(
        string $companyName = '.company-name',
        string $jobTitle = '.job-title',
        string $jobDescription = '.job-description',
    ): static {
        return $this->state(fn () => [
            'company_name_selector' => $companyName,
            'job_title_selector' => $jobTitle,
            'job_description_selector' => $jobDescription,
        ]);
    }
}
