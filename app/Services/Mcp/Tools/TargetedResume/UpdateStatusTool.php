<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Contracts\Mcp\AiToolHandlerContract;
use App\Enums\TargetedResumeApplicationStatus;
use App\Enums\TargetedResumeStatus;
use App\Models\AiConversation;
use App\Models\TargetedResumeStatusUpdate;

class UpdateStatusTool implements AiToolHandlerContract
{
    public function __construct(private AiConversation $conversation) {}

    public function name(): string
    {
        return 'update_status';
    }

    public function description(): string
    {
        return 'Log a job application status update. Call this when the candidate reports a status change, '
            . 'e.g. "I applied" → status=applied, "I have an interview on June 12th" → status=interviewing, '
            . 'occurred_at=2026-06-12, "I got rejected" → status=rejected. '
            . 'For `interviewing`, occurred_at should be the scheduled interview date. '
            . 'Valid statuses: applied, interviewing, interviewed, offered, accepted, hired, rejected.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => array_column(TargetedResumeApplicationStatus::cases(), 'value'),
                    'description' => 'The new application status.',
                ],
                'occurred_at' => [
                    'type' => 'string',
                    'description' => 'ISO date string (YYYY-MM-DD) for when the event happened or is scheduled. Defaults to today.',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Optional notes (e.g. interview round, rejection reason).',
                ],
            ],
            'required' => ['status'],
        ];
    }

    public function handle(array $input): array
    {
        $targetedResume = $this->conversation->targetedResume;

        if ($targetedResume === null) {
            return ['error' => 'No finalized resume found for this conversation. Save the tailored resume first.'];
        }

        $newStatus = TargetedResumeApplicationStatus::tryFrom((string) ($input['status'] ?? ''));

        if ($newStatus === null) {
            return ['error' => 'Invalid status value.'];
        }

        $currentStatus = TargetedResumeApplicationStatus::tryFrom($targetedResume->status->value);

        if ($currentStatus?->isTerminal()) {
            return [
                'error' => 'Cannot update status — application is already in a terminal state (' . $targetedResume->status->value . ').',
            ];
        }

        $occurredAt = isset($input['occurred_at']) && $input['occurred_at'] !== ''
            ? now()->parse($input['occurred_at'])
            : now();

        TargetedResumeStatusUpdate::create([
            'targeted_resume_id' => $targetedResume->id,
            'status' => $newStatus->value,
            'notes' => $input['notes'] ?? null,
            'occurred_at' => $occurredAt,
        ]);

        $targetedResume->update(['status' => TargetedResumeStatus::from($newStatus->value)]);

        return [
            'success' => true,
            'status' => $newStatus->value,
            'occurred_at' => $occurredAt->toDateString(),
            '_page_reload' => true,
        ];
    }
}
