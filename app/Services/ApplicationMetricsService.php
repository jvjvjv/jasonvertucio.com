<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Support\ApplicationStatusResolver;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ApplicationMetricsService
{
    /**
     * Progressive pipeline stages in funnel order. Each maps to a rank used to
     * determine how far an application advanced. "rejected" is an off-ramp and
     * has no rank.
     *
     * @var array<string, int>
     */
    private const STAGE_RANKS = [
        'applied' => 1,
        'interviewing' => 2,
        'interviewed' => 3,
        'offered' => 4,
        'accepted' => 5,
    ];

    /**
     * Build the full metrics payload for the dashboard, optionally limited
     * to the applications applied to within a period. Both ends are whole
     * days and inclusive; either may be left open. The period is applied
     * before any section is computed, so every section agrees on which
     * applications it covers. "Ghosted" is still judged against the real
     * present, not the end of the period.
     *
     * @return array{
     *     ghostedAfterDays: int,
     *     kpis: array<string, mixed>,
     *     funnel: array<int, array{stage: string, label: string, count: int}>,
     *     outcomes: array<int, array{outcome: string, label: string, count: int}>,
     *     overTime: array<int, array{period: string, count: int}>,
     *     cycleTimes: array<string, float|null>,
     *     timeline: array<int, array<string, mixed>>
     * }
     */
    public function build(?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $ghostedAfterDays = (int) config('resume.ghosted_after_days');

        $applications = $this->appliedWithin($this->appliedApplications(), $from, $to);

        return [
            'ghostedAfterDays' => $ghostedAfterDays,
            'kpis' => $this->kpis($applications, $ghostedAfterDays),
            'funnel' => $this->funnel($applications),
            'outcomes' => $this->outcomes($applications, $ghostedAfterDays),
            'overTime' => $this->overTime($applications),
            'cycleTimes' => $this->cycleTimes($applications),
            'timeline' => $this->timeline($applications, $ghostedAfterDays),
        ];
    }

    /**
     * Non-deleted applications that have actually been applied to — with a
     * targeted resume or with the main one — with their full status history
     * eager-loaded.
     *
     * @return Collection<int, Application>
     */
    private function appliedApplications(): Collection
    {
        return Application::query()
            ->with('statusUpdates')
            ->whereHas('statusUpdates', fn ($q) => $q->where('status', ApplicationStatus::Applied->value))
            ->get();
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @return Collection<int, Application>
     */
    private function appliedWithin(Collection $applications, ?CarbonInterface $from, ?CarbonInterface $to): Collection
    {
        if ($from === null && $to === null) {
            return $applications;
        }

        $start = $from?->copy()->startOfDay();
        $end = $to?->copy()->endOfDay();

        return $applications
            ->filter(function (Application $application) use ($start, $end): bool {
                $appliedAt = $this->appliedAt($application);

                return $appliedAt !== null
                    && ($start === null || $appliedAt->greaterThanOrEqualTo($start))
                    && ($end === null || $appliedAt->lessThanOrEqualTo($end));
            })
            ->values();
    }

    /**
     * The earliest "applied" status update for an application.
     */
    private function appliedAt(Application $application): ?Carbon
    {
        $update = $application->statusUpdates
            ->firstWhere(fn (ApplicationStatusUpdate $u) => $u->status->value === 'applied');

        return $update?->occurred_at;
    }

    /**
     * The distinct status values an application has ever recorded.
     *
     * @return array<int, string>
     */
    private function statusValues(Application $application): array
    {
        return $application->statusUpdates
            ->map(fn (ApplicationStatusUpdate $u) => $u->status->value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Highest pipeline rank an application reached (accepted/hired collapse to
     * "accepted"). Rejection does not advance the rank.
     */
    private function maxRank(Application $application): int
    {
        $rank = 0;

        foreach ($this->statusValues($application) as $status) {
            $normalized = $status === 'hired' ? 'accepted' : $status;
            $rank = max($rank, self::STAGE_RANKS[$normalized] ?? 0);
        }

        return $rank;
    }

    /**
     * The bucketed outcome for the donut: accepted, rejected, ghosted, or
     * in_progress.
     */
    private function outcome(Application $application, int $ghostedAfterDays): string
    {
        $statuses = $this->statusValues($application);

        if (in_array('accepted', $statuses, true) || in_array('hired', $statuses, true)) {
            return 'accepted';
        }

        if (in_array('rejected', $statuses, true)) {
            return 'rejected';
        }

        $latest = $application->statusUpdates->last();

        $display = ApplicationStatusResolver::resolve(
            $latest?->status->value,
            $latest?->occurred_at,
            $ghostedAfterDays,
        );

        return $display === ApplicationStatusResolver::GHOSTED ? 'ghosted' : 'in_progress';
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @return array<string, mixed>
     */
    private function kpis(Collection $applications, int $ghostedAfterDays): array
    {
        $total = $applications->count();

        if ($total === 0) {
            return [
                'totalApplied' => 0,
                'responseRate' => null,
                'interviewRate' => null,
                'offerRate' => null,
                'ghostRate' => null,
            ];
        }

        $responded = $applications->filter(fn (Application $r) => $this->maxRank($r) >= 2
            || in_array('rejected', $this->statusValues($r), true))->count();
        $interviewed = $applications->filter(fn (Application $r) => $this->maxRank($r) >= 2)->count();
        $offered = $applications->filter(fn (Application $r) => $this->maxRank($r) >= 4)->count();
        $ghosted = $applications->filter(fn (Application $r) => $this->outcome($r, $ghostedAfterDays) === 'ghosted')->count();

        return [
            'totalApplied' => $total,
            'responseRate' => $this->rate($responded, $total),
            'interviewRate' => $this->rate($interviewed, $total),
            'offerRate' => $this->rate($offered, $total),
            'ghostRate' => $this->rate($ghosted, $total),
        ];
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @return array<int, array{stage: string, label: string, count: int}>
     */
    private function funnel(Collection $applications): array
    {
        $stages = [
            'applied' => 'Applied',
            'interviewing' => 'Interviewing',
            'interviewed' => 'Interviewed',
            'offered' => 'Offered',
            'accepted' => 'Accepted',
        ];

        return collect($stages)->map(fn (string $label, string $stage) => [
            'stage' => $stage,
            'label' => $label,
            'count' => $applications
                ->filter(fn (Application $r) => $this->maxRank($r) >= self::STAGE_RANKS[$stage])
                ->count(),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @return array<int, array{outcome: string, label: string, count: int}>
     */
    private function outcomes(Collection $applications, int $ghostedAfterDays): array
    {
        $labels = [
            'accepted' => 'Accepted',
            'in_progress' => 'In progress',
            'rejected' => 'Rejected',
            'ghosted' => 'Ghosted',
        ];

        $counts = $applications
            ->groupBy(fn (Application $r) => $this->outcome($r, $ghostedAfterDays))
            ->map->count();

        return collect($labels)
            ->map(fn (string $label, string $outcome) => [
                'outcome' => $outcome,
                'label' => $label,
                'count' => $counts->get($outcome, 0),
            ])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @return array<int, array{period: string, count: int}>
     */
    private function overTime(Collection $applications): array
    {
        return $applications
            ->map(fn (Application $r) => $this->appliedAt($r))
            ->filter()
            ->groupBy(fn (Carbon $date) => $date->format('Y-m'))
            ->map->count()
            ->sortKeys()
            ->map(fn (int $count, string $period) => [
                'period' => $period,
                'count' => $count,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @return array<string, float|null>
     */
    private function cycleTimes(Collection $applications): array
    {
        $toResponse = [];
        $toRejection = [];
        $toOffer = [];

        foreach ($applications as $application) {
            $appliedAt = $this->appliedAt($application);

            if ($appliedAt === null) {
                continue;
            }

            $firstResponse = $application->statusUpdates
                ->first(fn (ApplicationStatusUpdate $u) => $u->status->value !== 'applied');

            if ($firstResponse !== null) {
                $toResponse[] = $appliedAt->diffInDays($firstResponse->occurred_at);
            }

            $rejection = $application->statusUpdates
                ->first(fn (ApplicationStatusUpdate $u) => $u->status->value === 'rejected');

            if ($rejection !== null) {
                $toRejection[] = $appliedAt->diffInDays($rejection->occurred_at);
            }

            $offer = $application->statusUpdates
                ->first(fn (ApplicationStatusUpdate $u) => $u->status->value === 'offered');

            if ($offer !== null) {
                $toOffer[] = $appliedAt->diffInDays($offer->occurred_at);
            }
        }

        return [
            'toFirstResponse' => $this->average($toResponse),
            'toRejection' => $this->average($toRejection),
            'toOffer' => $this->average($toOffer),
        ];
    }

    /**
     * @param  Collection<int, Application>  $applications
     * @return array<int, array<string, mixed>>
     */
    private function timeline(Collection $applications, int $ghostedAfterDays): array
    {
        $now = Carbon::now();

        return $applications
            ->map(function (Application $application) use ($ghostedAfterDays, $now) {
                $appliedAt = $this->appliedAt($application);

                if ($appliedAt === null) {
                    return null;
                }

                $updates = $application->statusUpdates->values();
                $outcome = $this->outcome($application, $ghostedAfterDays);
                $segments = [];

                foreach ($updates as $index => $update) {
                    $from = $update->occurred_at;
                    $isLast = $index === $updates->count() - 1;
                    $status = $update->status->value;

                    if (! $isLast) {
                        $to = $updates[$index + 1]->occurred_at;
                    } elseif ($update->status->isTerminal()) {
                        $to = $from;
                    } else {
                        $to = $now;
                        if ($outcome === 'ghosted') {
                            $status = ApplicationStatusResolver::GHOSTED;
                        }
                    }

                    $segments[] = [
                        'status' => $status,
                        'from' => $from->toIso8601String(),
                        'to' => $to->toIso8601String(),
                    ];
                }

                return [
                    'id' => $application->id,
                    'company' => $application->company_name,
                    'position' => $application->position,
                    'appliedAt' => $appliedAt->toIso8601String(),
                    'outcome' => $outcome,
                    'segments' => $segments,
                ];
            })
            ->filter()
            ->sortByDesc('appliedAt')
            ->values()
            ->all();
    }

    private function rate(int $count, int $total): float
    {
        return $total === 0 ? 0.0 : round($count / $total * 100, 1);
    }

    /**
     * @param  array<int, int>  $values
     */
    private function average(array $values): ?float
    {
        return $values === [] ? null : round(array_sum($values) / count($values), 1);
    }
}
