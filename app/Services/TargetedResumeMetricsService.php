<?php

namespace App\Services;

use App\Models\TargetedResume;
use App\Models\TargetedResumeStatusUpdate;
use App\Support\TargetedResumeStatusResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TargetedResumeMetricsService
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
     * Build the full metrics payload for the dashboard.
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
    public function build(): array
    {
        $ghostedAfterDays = (int) config('resume.ghosted_after_days');

        $applications = $this->appliedResumes();

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
     * Targeted resumes that have actually been applied to, with their full
     * status history eager-loaded.
     *
     * @return Collection<int, TargetedResume>
     */
    private function appliedResumes(): Collection
    {
        return TargetedResume::query()
            ->with('statusUpdates')
            ->whereHas('statusUpdates', fn ($q) => $q->where('status', 'applied'))
            ->get();
    }

    /**
     * The earliest "applied" status update for an application.
     */
    private function appliedAt(TargetedResume $resume): ?Carbon
    {
        $update = $resume->statusUpdates
            ->firstWhere(fn (TargetedResumeStatusUpdate $u) => $u->status->value === 'applied');

        return $update?->occurred_at;
    }

    /**
     * The distinct status values an application has ever recorded.
     *
     * @return array<int, string>
     */
    private function statusValues(TargetedResume $resume): array
    {
        return $resume->statusUpdates
            ->map(fn (TargetedResumeStatusUpdate $u) => $u->status->value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Highest pipeline rank an application reached (accepted/hired collapse to
     * "accepted"). Rejection does not advance the rank.
     */
    private function maxRank(TargetedResume $resume): int
    {
        $rank = 0;

        foreach ($this->statusValues($resume) as $status) {
            $normalized = $status === 'hired' ? 'accepted' : $status;
            $rank = max($rank, self::STAGE_RANKS[$normalized] ?? 0);
        }

        return $rank;
    }

    /**
     * The bucketed outcome for the donut: accepted, rejected, ghosted, or
     * in_progress.
     */
    private function outcome(TargetedResume $resume, int $ghostedAfterDays): string
    {
        $statuses = $this->statusValues($resume);

        if (in_array('accepted', $statuses, true) || in_array('hired', $statuses, true)) {
            return 'accepted';
        }

        if (in_array('rejected', $statuses, true)) {
            return 'rejected';
        }

        $latest = $resume->statusUpdates->last();

        $display = TargetedResumeStatusResolver::resolve(
            $latest?->status->value,
            $latest?->occurred_at,
            $ghostedAfterDays,
        );

        return $display === TargetedResumeStatusResolver::GHOSTED ? 'ghosted' : 'in_progress';
    }

    /**
     * @param  Collection<int, TargetedResume>  $applications
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

        $responded = $applications->filter(fn (TargetedResume $r) => $this->maxRank($r) >= 2
            || in_array('rejected', $this->statusValues($r), true))->count();
        $interviewed = $applications->filter(fn (TargetedResume $r) => $this->maxRank($r) >= 2)->count();
        $offered = $applications->filter(fn (TargetedResume $r) => $this->maxRank($r) >= 4)->count();
        $ghosted = $applications->filter(fn (TargetedResume $r) => $this->outcome($r, $ghostedAfterDays) === 'ghosted')->count();

        return [
            'totalApplied' => $total,
            'responseRate' => $this->rate($responded, $total),
            'interviewRate' => $this->rate($interviewed, $total),
            'offerRate' => $this->rate($offered, $total),
            'ghostRate' => $this->rate($ghosted, $total),
        ];
    }

    /**
     * @param  Collection<int, TargetedResume>  $applications
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
                ->filter(fn (TargetedResume $r) => $this->maxRank($r) >= self::STAGE_RANKS[$stage])
                ->count(),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, TargetedResume>  $applications
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
            ->groupBy(fn (TargetedResume $r) => $this->outcome($r, $ghostedAfterDays))
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
     * @param  Collection<int, TargetedResume>  $applications
     * @return array<int, array{period: string, count: int}>
     */
    private function overTime(Collection $applications): array
    {
        return $applications
            ->map(fn (TargetedResume $r) => $this->appliedAt($r))
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
     * @param  Collection<int, TargetedResume>  $applications
     * @return array<string, float|null>
     */
    private function cycleTimes(Collection $applications): array
    {
        $toResponse = [];
        $toRejection = [];
        $toOffer = [];

        foreach ($applications as $resume) {
            $appliedAt = $this->appliedAt($resume);

            if ($appliedAt === null) {
                continue;
            }

            $firstResponse = $resume->statusUpdates
                ->first(fn (TargetedResumeStatusUpdate $u) => $u->status->value !== 'applied');

            if ($firstResponse !== null) {
                $toResponse[] = $appliedAt->diffInDays($firstResponse->occurred_at);
            }

            $rejection = $resume->statusUpdates
                ->first(fn (TargetedResumeStatusUpdate $u) => $u->status->value === 'rejected');

            if ($rejection !== null) {
                $toRejection[] = $appliedAt->diffInDays($rejection->occurred_at);
            }

            $offer = $resume->statusUpdates
                ->first(fn (TargetedResumeStatusUpdate $u) => $u->status->value === 'offered');

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
     * @param  Collection<int, TargetedResume>  $applications
     * @return array<int, array<string, mixed>>
     */
    private function timeline(Collection $applications, int $ghostedAfterDays): array
    {
        $now = Carbon::now();

        return $applications
            ->map(function (TargetedResume $resume) use ($ghostedAfterDays, $now) {
                $appliedAt = $this->appliedAt($resume);

                if ($appliedAt === null) {
                    return null;
                }

                $updates = $resume->statusUpdates->values();
                $outcome = $this->outcome($resume, $ghostedAfterDays);
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
                            $status = TargetedResumeStatusResolver::GHOSTED;
                        }
                    }

                    $segments[] = [
                        'status' => $status,
                        'from' => $from->toIso8601String(),
                        'to' => $to->toIso8601String(),
                    ];
                }

                return [
                    'id' => $resume->id,
                    'company' => $resume->company_name,
                    'position' => $resume->position,
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
