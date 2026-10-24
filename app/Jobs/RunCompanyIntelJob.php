<?php

namespace App\Jobs;

use App\Enums\RoleIntelStatus;
use App\Models\RoleIntel;
use App\Services\CompanyIntel\CompanyIntelService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs an intel session's pending turn in the queue.
 *
 * One try: a retried agent turn would repeat its tool writes, so a failure is
 * left for the admin to retry deliberately. The timeout sits under the
 * production worker's 299 s, so this job's own timeout fires first and
 * failed() records it.
 */
class RunCompanyIntelJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 280;

    public bool $failOnTimeout = true;

    public function __construct(public int $roleIntelId) {}

    public function handle(CompanyIntelService $companyIntel): void
    {
        $roleIntel = RoleIntel::query()->find($this->roleIntelId);

        if ($roleIntel === null || $roleIntel->status !== RoleIntelStatus::Queued) {
            return;
        }

        $companyIntel->runInBackground($roleIntel);
    }

    public function failed(?Throwable $exception): void
    {
        $roleIntel = RoleIntel::query()->find($this->roleIntelId);

        if ($roleIntel === null || $roleIntel->status === RoleIntelStatus::Ready) {
            return;
        }

        $reason = $exception !== null && str_contains($exception::class, 'Timeout')
            ? 'Research timed out.'
            : 'Research failed.';

        app(CompanyIntelService::class)->markFailed($roleIntel, $reason);
    }
}
