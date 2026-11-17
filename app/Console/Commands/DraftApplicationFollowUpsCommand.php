<?php

namespace App\Console\Commands;

use App\Jobs\DraftApplicationFollowUpJob;
use App\Services\ApplicationFollowUpService;
use Illuminate\Console\Command;

/**
 * Queues a follow-up draft for every application that is due one. It does no
 * AI work itself, so a slow model cannot stall the scheduler. Scheduled
 * daily; safe to run by hand and to run twice, because each job re-checks
 * that its application is still due before storing anything.
 */
class DraftApplicationFollowUpsCommand extends Command
{
    protected $signature = 'applications:draft-follow-ups
                            {--dry-run : List the applications that are due, without queueing anything}';

    protected $description = 'Queue AI-drafted follow-ups for applications that are due one (nothing is sent without approval)';

    public function handle(ApplicationFollowUpService $followUps): int
    {
        $due = $followUps->dueApplications();
        $dryRun = (bool) $this->option('dry-run');

        if ($due->isNotEmpty()) {
            $this->table(['ID', 'Company', 'Position', 'Status'], $due->map(fn ($application): array => [
                $application->id,
                $application->company_name,
                $application->position,
                $application->status->value,
            ])->all());
        }

        if (! $dryRun) {
            foreach ($due as $application) {
                DraftApplicationFollowUpJob::dispatch($application->id);
            }
        }

        $this->info($dryRun
            ? "{$due->count()} application(s) are due a follow-up."
            : "Queued {$due->count()} follow-up draft(s).");

        return self::SUCCESS;
    }
}
