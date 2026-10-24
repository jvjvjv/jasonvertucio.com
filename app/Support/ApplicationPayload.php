<?php

namespace App\Support;

use App\Models\AiConversation;
use App\Models\Application;
use App\Models\ResumeVersion;
use Illuminate\Support\Collection;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * The shapes the application pages and endpoints share, so the list, the
 * Discussion page and every status endpoint describe an application the
 * same way.
 */
class ApplicationPayload
{
    /**
     * An application's status as the status endpoints report it after a
     * change.
     *
     * @return array{
     *     success: true,
     *     status: string,
     *     status_updates: array<int, array{id: int, status: string, notes: ?string, occurred_at: ?string}>,
     *     allowed_next_statuses: array<int, string>
     * }
     */
    public function statusState(Application $application): array
    {
        return [
            'success' => true,
            'status' => $application->status->value,
            'status_updates' => $this->statusUpdates($application),
            'allowed_next_statuses' => $this->allowedNextStatuses($application),
        ];
    }

    /**
     * The status history, oldest first.
     *
     * @return array<int, array{id: int, status: string, notes: ?string, occurred_at: ?string}>
     */
    public function statusUpdates(Application $application): array
    {
        return $application->statusUpdates
            ->map(fn ($statusUpdate): array => [
                'id' => $statusUpdate->id,
                'status' => $statusUpdate->status->value,
                'notes' => $statusUpdate->notes,
                'occurred_at' => $statusUpdate->occurred_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * The statuses offered as the next history entry: none once terminal.
     *
     * @return array<int, string>
     */
    public function allowedNextStatuses(Application $application): array
    {
        if ($application->status->isTerminal()) {
            return [];
        }

        return array_column($application->status->allowedNext(), 'value');
    }

    /**
     * An AI session's token usage and cost.
     *
     * @return array{input_tokens: ?int, output_tokens: ?int, total_tokens: ?int, cost_usd: ?float, synced_at: ?string}
     */
    public function usage(AiConversation $conversation): array
    {
        return [
            'input_tokens' => $conversation->usage_input_tokens,
            'output_tokens' => $conversation->usage_output_tokens,
            'total_tokens' => $conversation->usage_total_tokens,
            'cost_usd' => $conversation->usage_cost_usd !== null ? (float) $conversation->usage_cost_usd : null,
            'synced_at' => $conversation->usage_synced_at?->toIso8601String(),
        ];
    }

    /**
     * The AI systems a session may be started with: those with a system
     * prompt assigned, or that are a feature default.
     *
     * @return Collection<int, array{id: int, name: string, model: string}>
     */
    public function selectableSystems(): Collection
    {
        return AiSystem::active()
            ->where(function ($query): void {
                $query->whereNotNull('system_prompt_id')
                    ->orWhereHas('featureDefaults');
            })
            ->orderBy('name')
            ->get()
            ->map(fn (AiSystem $system): array => [
                'id' => $system->id,
                'name' => $system->name,
                'model' => $system->model,
            ]);
    }

    /**
     * Every resume version, newest first, for choosing the one that was sent.
     *
     * @return Collection<int, array{id: int, version: string, is_current: bool}>
     */
    public function resumeVersions(): Collection
    {
        return ResumeVersion::query()
            ->orderByDesc('id')
            ->get(['id', 'version', 'is_current'])
            ->map(fn (ResumeVersion $resumeVersion): array => [
                'id' => $resumeVersion->id,
                'version' => $resumeVersion->version,
                'is_current' => (bool) $resumeVersion->is_current,
            ]);
    }
}
