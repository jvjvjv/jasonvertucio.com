<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Services\ApplicationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Records a `ghosted` entry on every application that has waited on the
 * employer for longer than `config('resume.ghosted_after_days')`. Scheduled
 * daily; safe to run by hand and to run twice.
 */
class MarkGhostedApplicationsCommand extends Command
{
    protected $signature = 'applications:mark-ghosted
                            {--dry-run : List the applications that would be marked, without changing anything}';

    protected $description = 'Mark applications ghosted after the configured number of days with no status update';

    public function handle(ApplicationService $applications): int
    {
        $days = (int) config('resume.ghosted_after_days');
        $dryRun = (bool) $this->option('dry-run');
        $rows = [];
        $marked = 0;

        $this->silentApplications($days)->chunkById(100, function (Collection $chunk) use ($applications, $days, $dryRun, &$rows, &$marked): void {
            foreach ($chunk as $application) {
                if ($dryRun) {
                    $rows[] = $this->row($application, $application->latestStatusUpdate?->occurred_at->copy()->addDays($days)->toDateString());

                    continue;
                }

                $row = $this->row($application, null);
                $entry = $applications->markGhosted($application, $days);

                if ($entry !== null) {
                    $marked++;
                    $row[4] = $entry->occurred_at->toDateString();
                    $rows[] = $row;
                }
            }
        });

        if ($rows !== []) {
            $this->table(['ID', 'Company', 'Position', 'Was', 'Ghosted on'], $rows);
        }

        $this->info($dryRun
            ? count($rows)." application(s) would be marked ghosted after {$days} days with no update."
            : "Marked {$marked} application(s) ghosted after {$days} days with no update.");

        return self::SUCCESS;
    }

    /**
     * Non-deleted applications waiting on the employer whose latest status
     * entry is older than the threshold.
     *
     * @return Builder<Application>
     */
    private function silentApplications(int $days): Builder
    {
        $latestEntry = ApplicationStatusUpdate::query()
            ->selectRaw('MAX(occurred_at)')
            ->whereColumn('application_id', 'applications.id');

        return Application::query()
            ->with('latestStatusUpdate')
            ->whereIn('status', ApplicationService::GHOSTABLE_STATUSES)
            ->where($latestEntry, '<', now()->subDays($days));
    }

    /**
     * @return array{int, string, string, string, ?string}
     */
    private function row(Application $application, ?string $ghostedOn): array
    {
        return [
            $application->id,
            $application->company_name,
            $application->position,
            $application->status->value,
            $ghostedOn,
        ];
    }
}
