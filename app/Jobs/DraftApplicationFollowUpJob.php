<?php

namespace App\Jobs;

use App\Models\Application;
use App\Services\ApplicationFollowUpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drafts one application's follow-up in the queue, so a slow model never
 * stalls the scheduler.
 *
 * One try: a failed draft is simply not stored, and the application is due
 * again on the next daily run. The timeout sits under the production
 * worker's 299 s, so this job's own timeout fires first.
 */
class DraftApplicationFollowUpJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 280;

    public bool $failOnTimeout = true;

    public function __construct(public int $applicationId) {}

    public function handle(ApplicationFollowUpService $followUps): void
    {
        $application = Application::query()->find($this->applicationId);

        if ($application === null) {
            return;
        }

        $followUps->draft($application);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Application follow-up drafting job failed.', [
            'application_id' => $this->applicationId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
