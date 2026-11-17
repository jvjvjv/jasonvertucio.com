<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * How many of the owner's applications a company is currently ghosting, and
 * what that costs the company's other applications in displayed fit score.
 *
 * The tally is derived from stored statuses every time it is asked for, so a
 * company that replies (moving its application out of `ghosted`) or an
 * application that is deleted drops off it with no bookkeeping. Two
 * applications are at the same company when their Role intel points at the
 * same researched Company, or when either's company name equals the other's
 * company name or researched Company name, ignoring case and surrounding
 * whitespace. An application never counts toward its own tally.
 */
class CompanyGhostingTally
{
    /**
     * The tally for each of the given applications, from one query.
     *
     * @param  Collection<int, Application>  $applications
     * @return array<int, int> application id => tally
     */
    public function forApplications(Collection $applications): array
    {
        $index = $this->ghostedByKey();

        if ($index === []) {
            return $applications->mapWithKeys(fn (Application $application): array => [$application->id => 0])->all();
        }

        $applications = EloquentCollection::make($applications->all())->loadMissing('roleIntel.company:id,name');

        return $applications
            ->mapWithKeys(fn (Application $application): array => [$application->id => $this->tallyFrom($index, $application)])
            ->all();
    }

    public function forApplication(Application $application): int
    {
        return $this->forApplications(Collection::make([$application]))[$application->id];
    }

    /**
     * Points taken off the displayed fit score for a tally.
     */
    public function penaltyFor(int $tally): int
    {
        return max(0, $tally) * (int) config('resume.ghosted_fit_penalty');
    }

    /**
     * The fit score to display: the stored score less the penalty, never
     * below 1. Null when there is no fit score yet.
     */
    public function adjustedFitScore(?int $fitScore, int $tally): ?int
    {
        if ($fitScore === null) {
            return null;
        }

        return max(1, $fitScore - $this->penaltyFor($tally));
    }

    /**
     * The `ghosting` object sent to the admin pages.
     *
     * @return array{count: int, penalty: int, adjusted_fit_score: ?int}
     */
    public function summary(?int $fitScore, int $tally): array
    {
        return [
            'count' => $tally,
            'penalty' => $this->penaltyFor($tally),
            'adjusted_fit_score' => $this->adjustedFitScore($fitScore, $tally),
        ];
    }

    /**
     * @param  array<string, list<int>>  $index
     */
    private function tallyFrom(array $index, Application $application): int
    {
        $ghostedIds = [];

        foreach ($this->keysFor($application) as $key) {
            foreach ($index[$key] ?? [] as $id) {
                $ghostedIds[$id] = true;
            }
        }

        unset($ghostedIds[$application->id]);

        return count($ghostedIds);
    }

    /**
     * Ghosted application ids by company key.
     *
     * @return array<string, list<int>>
     */
    private function ghostedByKey(): array
    {
        $index = [];

        Application::query()
            ->where('status', ApplicationStatus::Ghosted)
            ->with('roleIntel.company:id,name')
            ->get(['id', 'company_name'])
            ->each(function (Application $ghosted) use (&$index): void {
                foreach ($this->keysFor($ghosted) as $key) {
                    $index[$key][] = $ghosted->id;
                }
            });

        return $index;
    }

    /**
     * @return list<string>
     */
    private function keysFor(Application $application): array
    {
        $keys = [];
        $company = $application->roleIntel?->company;

        if ($company !== null) {
            $keys[] = 'company:'.$company->id;

            if (trim((string) $company->name) !== '') {
                $keys[] = 'name:'.self::normalizeName($company->name);
            }
        }

        $name = $application->knownCompanyName();

        if ($name !== null && trim($name) !== '') {
            $keys[] = 'name:'.self::normalizeName($name);
        }

        return array_values(array_unique($keys));
    }

    private static function normalizeName(string $name): string
    {
        return mb_strtolower(trim($name));
    }
}
