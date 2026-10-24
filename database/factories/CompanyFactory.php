<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();
        $domain = Str::slug($name).'-'.Str::lower(Str::random(6)).'.example';

        return [
            'name' => $name,
            'website_domain' => $domain,
            'website_url' => "https://{$domain}",
            'headquarters' => fake()->city(),
            'employee_count' => '1,001–5,000',
            'revenue' => null,
            'industry' => 'Software',
            'founded_year' => fake()->numberBetween(1950, 2020),
            'ownership' => 'private',
            'ticker' => null,
            'summary' => fake()->sentence(),
            'researched_at' => now(),
        ];
    }
}
