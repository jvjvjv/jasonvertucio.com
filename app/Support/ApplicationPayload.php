<?php

namespace App\Support;

use App\Enums\FollowUpStatus;
use App\Models\AiConversation;
use App\Models\Application;
use App\Models\ApplicationContact;
use App\Models\ApplicationFollowUp;
use App\Services\ApplicationFollowUpService;
use App\Models\ResumeVersion;
use App\Services\TargetedResumeSystems;
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
     * An application's contacts and follow-ups, as the Discussion page shows
     * them and every contact and follow-up endpoint reports them after a
     * change. Contacts appear here only, behind `edit-resume`.
     *
     * @return array{
     *     success: true,
     *     follow_ups_enabled: bool,
     *     contacts: array<int, array{id: int, email: string, name: ?string, role: ?string, notes: ?string}>,
     *     pending_follow_up: ?array{id: int, status: string, subject: string, body: string, recipients: ?array<int, string>, drafted_at: ?string, sent_at: ?string, discarded_at: ?string, discard_reason: ?string, failure_reason: ?string},
     *     follow_up_history: array<int, array{id: int, status: string, subject: string, body: string, recipients: ?array<int, string>, drafted_at: ?string, sent_at: ?string, discarded_at: ?string, discard_reason: ?string, failure_reason: ?string}>,
     *     follow_up_system_configured: bool,
     *     sent_since_status_change: int,
     *     max_per_status: int,
     *     cadence_days: int
     * }
     */
    public function followUpState(Application $application): array
    {
        $followUps = app(ApplicationFollowUpService::class);
        $all = $application->followUps()->get();
        $pending = $all->last(fn (ApplicationFollowUp $followUp): bool => $followUp->status === FollowUpStatus::Pending);

        return [
            'success' => true,
            'follow_ups_enabled' => (bool) $application->follow_ups_enabled,
            'contacts' => $application->contacts()->get()
                ->map(fn (ApplicationContact $contact): array => [
                    'id' => $contact->id,
                    'email' => $contact->email,
                    'name' => $contact->name,
                    'role' => $contact->role,
                    'notes' => $contact->notes,
                ])
                ->values()
                ->all(),
            'pending_follow_up' => $pending !== null ? $this->followUp($pending) : null,
            'follow_up_history' => $all
                ->filter(fn (ApplicationFollowUp $followUp): bool => in_array($followUp->status, [FollowUpStatus::Sending, FollowUpStatus::Sent, FollowUpStatus::Failed], true))
                ->sortByDesc(fn (ApplicationFollowUp $followUp): int => ($followUp->sent_at ?? $followUp->updated_at)?->getTimestamp() ?? 0)
                ->map(fn (ApplicationFollowUp $followUp): array => $this->followUp($followUp))
                ->values()
                ->all(),
            'follow_up_system_configured' => $followUps->system() !== null,
            'sent_since_status_change' => $followUps->sentSinceStatusChange($application),
            'max_per_status' => (int) config('resume.follow_ups.max_per_status'),
            'cadence_days' => (int) config('resume.follow_ups.cadence_days'),
        ];
    }

    /**
     * @return array{id: int, status: string, subject: string, body: string, recipients: ?array<int, string>, drafted_at: ?string, sent_at: ?string, discarded_at: ?string, discard_reason: ?string, failure_reason: ?string}
     */
    private function followUp(ApplicationFollowUp $followUp): array
    {
        return [
            'id' => $followUp->id,
            'status' => $followUp->status->value,
            'subject' => $followUp->subject,
            'body' => $followUp->body,
            'recipients' => $followUp->recipients,
            'drafted_at' => $followUp->drafted_at?->toIso8601String(),
            'sent_at' => $followUp->sent_at?->toIso8601String(),
            'discarded_at' => $followUp->discarded_at?->toIso8601String(),
            'discard_reason' => $followUp->discard_reason?->value,
            'failure_reason' => $followUp->failure_reason,
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
     * An AI session's token usage.
     *
     * @return array{input_tokens: ?int, output_tokens: ?int, total_tokens: ?int, synced_at: ?string}
     */
    public function usage(AiConversation $conversation): array
    {
        return [
            'input_tokens' => $conversation->usage_input_tokens,
            'output_tokens' => $conversation->usage_output_tokens,
            'total_tokens' => $conversation->usage_total_tokens,
            'synced_at' => $conversation->usage_synced_at?->toIso8601String(),
        ];
    }

    /**
     * The AI systems a targeted-resume session may be started with.
     *
     * @return Collection<int, array{id: int, name: string, model: string}>
     */
    public function selectableSystems(): Collection
    {
        return app(TargetedResumeSystems::class)->selectable()
            ->toBase()
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
