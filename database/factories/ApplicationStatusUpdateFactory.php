<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationStatusUpdate>
 */
class ApplicationStatusUpdateFactory extends Factory
{
    protected $model = ApplicationStatusUpdate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'status' => ApplicationStatus::Applied,
            'notes' => fake()->optional()->sentence(),
            'occurred_at' => now(),
        ];
    }

    public function status(ApplicationStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
