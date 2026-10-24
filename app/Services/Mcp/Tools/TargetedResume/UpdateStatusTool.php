<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Enums\ApplicationStatus;
use App\Exceptions\ApplicationException;
use App\Exceptions\TerminalApplicationException;
use App\Services\ApplicationService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update-status')]
#[Description(
    'Log a job application status update. Call this when the candidate reports a status change, '
    .'e.g. "I applied" → status=applied, "I have an interview on June 12th" → status=interviewing, '
    .'occurred_at=2026-06-12, "I got rejected" → status=rejected. '
    .'For `interviewing`, occurred_at should be the scheduled interview date. '
    .'Valid statuses: applied, interviewing, interviewed, offered, accepted, hired, rejected.'
)]
class UpdateStatusTool extends AuthorizedResumeTool
{
    public function __construct(
        ToolContext $context,
        private ApplicationService $applicationService,
    ) {
        parent::__construct($context);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(array_column(ApplicationStatus::pipeline(), 'value'))
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

        $application = $this->application();

        if ($application === null) {
            return Response::error('No application found for this conversation.');
        }

        $newStatus = ApplicationStatus::tryFrom((string) ($request->get('status') ?? ''));

        if ($newStatus === null || ! $newStatus->isPipeline()) {
            return Response::error('Invalid status value.');
        }

        try {
            $occurredAt = $this->resolveOccurredAt($request->get('occurred_at'));
        } catch (\Throwable) {
            return Response::error('occurred_at must be a valid date (YYYY-MM-DD).');
        }

        try {
            $this->applicationService->addStatusUpdate(
                $application,
                $newStatus,
                $request->get('notes') !== null ? (string) $request->get('notes') : null,
                $occurredAt,
            );
        } catch (TerminalApplicationException) {
            return Response::error(
                'Cannot update status — application is already in a terminal state ('.$application->status->value.').'
            );
        } catch (ApplicationException $exception) {
            return Response::error($exception->getMessage());
        }

        return Response::structured([
            'success' => true,
            'status' => $newStatus->value,
            'occurred_at' => $occurredAt->toDateString(),
            '_page_reload' => true,
        ]);
    }

    /**
     * A bare date is pinned to midday so it does not drift to the previous
     * day when shown in another timezone; a value that already carries a
     * time is taken as given.
     */
    private function resolveOccurredAt(mixed $input): CarbonInterface
    {
        $input = is_string($input) ? trim($input) : '';

        if ($input === '') {
            return now();
        }

        $hasTime = str_contains($input, ' ') || stripos($input, 'T') !== false;

        return Carbon::parse($hasTime ? $input : $input.' 12:00:00');
    }
}
