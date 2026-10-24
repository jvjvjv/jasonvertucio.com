<?php

namespace Database\Factories;

use App\Models\McpCall;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<McpCall>
 */
class McpCallFactory extends Factory
{
    protected $model = McpCall::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_id' => $this->faker->uuid(),
            'method' => 'tools/call',
            'tool_name' => 'get-resume-data',
            'user_id' => null,
            'client_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'client_name' => 'claude-desktop',
            'client_version' => '1.0.0',
            'outcome' => McpCall::OUTCOME_OK,
            'status_code' => 200,
            'duration_ms' => $this->faker->numberBetween(1, 400),
            'created_at' => now(),
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn (): array => ['user_id' => null]);
    }

    public function throttled(): static
    {
        return $this->state(fn (): array => [
            'outcome' => McpCall::OUTCOME_THROTTLED,
            'status_code' => 429,
        ]);
    }

    public function unidentifiedClient(): static
    {
        return $this->state(fn (): array => [
            'client_name' => null,
            'client_version' => null,
        ]);
    }
}
