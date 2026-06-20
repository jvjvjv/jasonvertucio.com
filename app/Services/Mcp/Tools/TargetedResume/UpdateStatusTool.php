<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Enums\TargetedResumeApplicationStatus;
use App\Enums\TargetedResumeStatus;
use App\Models\TargetedResumeStatusUpdate;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update-status')]
#[Description(
    'Log a job application status update. Call this when the candidate reports a status change, '
    . 'e.g. "I applied" → status=applied, "I have an interview on June 12th" → status=interviewing, '
    . 'occurred_at=2026-06-12, "I got rejected" → status=rejected. '
    . 'For `interviewing`, occurred_at should be the scheduled interview date. '
    . 'Valid statuses: applied, interviewing, interviewed, offered, accepted, hired, rejected.'
)]
class UpdateStatusTool extends AuthorizedResumeTool
{
    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(array_column(TargetedResumeApplicationStatus::cases(), 'value'))
                ->description('The new application status.')
                ->required(),
            'occurred_at' => $schema->string()
                ->description('ISO date string (YYYY-MM-DD) for when the event happened or is scheduled. Defaults to today.'),
            'notes' => $schema->string()
                ->description('Optional notes (e.g. interview round, rejection reason).'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $targetedResume = $this->context->conversation?->targetedResume;

        if ($targetedResume === null) {
            return Response::error('No finalized resume found for this conversation. Save the tailored resume first.');
        }

        $newStatus = TargetedResumeApplicationStatus::tryFrom((string) ($request->get('status') ?? ''));

        if ($newStatus === null) {
            return Response::error('Invalid status value.');
        }

        $currentStatus = TargetedResumeApplicationStatus::tryFrom($targetedResume->status->value);

        if ($currentStatus?->isTerminal()) {
            return Response::error(
                'Cannot update status — application is already in a terminal state (' . $targetedResume->status->value . ').'
            );
        }

        $occurredAtInput = $request->get('occurred_at');

        $occurredAt = isset($occurredAtInput) && $occurredAtInput !== ''
            ? $occurredAtInput
            ? stripos($occurredAtInput, ' ') || stripos($occurredAtInput, 'T')
            : now()->parse($occurredAtInput . ' 12:00:00')
            : now();

        TargetedResumeStatusUpdate::create([
            'targeted_resume_id' => $targetedResume->id,
            'status' => $newStatus->value,
            'notes' => $request->get('notes'),
            'occurred_at' => $occurredAt,
        ]);

        $targetedResume->update(['status' => TargetedResumeStatus::from($newStatus->value)]);

        return Response::structured([
            'success' => true,
            'status' => $newStatus->value,
            'occurred_at' => $occurredAt->toDateString(),
            '_page_reload' => true,
        ]);
    }
}
