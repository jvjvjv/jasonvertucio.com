<?php

namespace Database\Factories;

use App\Enums\CompanyInsightCategory;
use App\Models\Company;
use App\Models\CompanyInsight;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyInsight>
 */
class CompanyInsightFactory extends Factory
{
    protected $model = CompanyInsight::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'category' => fake()->randomElement(CompanyInsightCategory::cases()),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'source_url' => fake()->optional()->url(),
        ];
    }
}
