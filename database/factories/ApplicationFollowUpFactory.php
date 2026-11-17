<?php

namespace Database\Factories;

use App\Enums\FollowUpDiscardReason;
use App\Enums\FollowUpStatus;
use App\Models\Application;
use App\Models\ApplicationFollowUp;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationFollowUp>
 */
class ApplicationFollowUpFactory extends Factory
{
    protected $model = ApplicationFollowUp::class;

    /**
     * Defaults to a pending draft.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'status' => FollowUpStatus::Pending,
            'subject' => 'Following up: '.fake()->jobTitle(),
            'body' => fake()->paragraphs(2, true),
            'recipients' => null,
            'ai_system_id' => null,
            'drafted_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FollowUpStatus::Pending,
        ]);
    }

    /**
     * A follow-up that went out, to the given addresses (one fake address
     * when none are given), at the given time (now by default).
     *
     * @param  list<string>|null  $recipients
     */
    public function sent(?CarbonInterface $sentAt = null, ?array $recipients = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FollowUpStatus::Sent,
            'recipients' => $recipients ?? [fake()->safeEmail()],
            'sent_at' => $sentAt ?? now(),
        ]);
    }

    public function failed(string $reason = 'Connection refused.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FollowUpStatus::Failed,
            'recipients' => [fake()->safeEmail()],
            'failure_reason' => $reason,
        ]);
    }

    public function discarded(FollowUpDiscardReason $reason = FollowUpDiscardReason::Manual): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FollowUpStatus::Discarded,
            'discarded_at' => now(),
            'discard_reason' => $reason,
        ]);
    }
}
