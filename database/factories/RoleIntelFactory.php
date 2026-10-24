<?php

namespace Database\Factories;

use App\Enums\RoleIntelStatus;
use App\Models\AiConversation;
use App\Models\Application;
use App\Models\Company;
use App\Models\RoleIntel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleIntel>
 */
class RoleIntelFactory extends Factory
{
    protected $model = RoleIntel::class;

    /**
     * Defaults to finished intel with a company and a session.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'company_id' => Company::factory(),
            'ai_conversation_id' => AiConversation::factory()->state(['feature' => 'company-intel']),
            'status' => RoleIntelStatus::Ready,
            'summary' => fake()->paragraph(),
            'responsibilities' => fake()->paragraph(),
        ];
    }

    public function withoutCompany(): static
    {
        return $this->state(['company_id' => null]);
    }

    public function status(RoleIntelStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
