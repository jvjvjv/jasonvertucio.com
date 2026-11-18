<?php

namespace App\Services\JobSearch\InsightGlobal;

use Carbon\CarbonImmutable;

/**
 * Runs an Insight Global search end to end: every keyword and page, the date
 * window, de-duplication, the result cap, then details for the returned jobs.
 *
 * Searches run before any details, so the request budget is spent on jobs
 * that will actually be returned. Nothing is stored or cached.
 */
class InsightGlobalSearch
{
    public const NO_RESULTS_MESSAGE = 'No matching jobs were found.';

    /** @var array<string, array{entry: array<string, mixed>, posted: CarbonImmutable, keywords: list<string>}> */
    private array $found = [];

    /** @var list<array<string, mixed>> */
    private array $errors = [];

    /** @var list<string> */
    private array $skipped = [];

    private int $successfulSearches = 0;

    /** @var array<string, true> Requisition ids dropped by the US-only filter */
    private array $excludedNonUs = [];

    public function __construct(
        private readonly InsightGlobalClient $client,
        private readonly InsightGlobalJobNormalizer $normalizer,
    ) {}

    public function run(InsightGlobalSearchCriteria $criteria): SearchOutcome
    {
        $this->found = [];
        $this->errors = [];
        $this->skipped = [];
        $this->successfulSearches = 0;
        $this->excludedNonUs = [];

        foreach ($criteria->keywords as $keyword) {
            $this->searchKeyword($criteria, $keyword);
        }

        $ordered = array_values($this->found);
        usort($ordered, static fn (array $a, array $b): int => $b['posted'] <=> $a['posted']);

        $totalFound = count($ordered);
        $returned = array_slice($ordered, 0, $criteria->maxResults);

        $jobs = [];
        $unrecognized = [];

        foreach ($returned as $hit) {
            $jobs[] = $this->buildJob($criteria, $hit);

            if ($this->normalizer->hasUnrecognizedJobType($hit['entry'])) {
                $type = trim((string) $hit['entry']['jobType']);
                $unrecognized[$type] ??= ['job_type' => $type, 'count' => 0, 'job_ids' => []];
                $unrecognized[$type]['count']++;
                $unrecognized[$type]['job_ids'][] = (string) $hit['entry']['requisitionId'];
            }
        }

        $payload = [
            'search_window' => [
                'today' => $criteria->today->toDateString(),
                'today_source' => $criteria->todaySupplied ? 'supplied' : 'default',
                'days_back' => $criteria->daysBack,
                'days_back_source' => $criteria->daysBackSupplied ? 'supplied' : 'default',
                'from' => $criteria->windowStart()->toIso8601String(),
                'to' => $criteria->windowEnd()->toIso8601String(),
            ],
            'keywords_searched' => $criteria->keywords,
            'filters' => $criteria->filtersSummary(),
            'total_found' => $totalFound,
            'returned' => count($jobs),
            'capped' => $totalFound > $criteria->maxResults,
            'complete' => $this->skipped === [],
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'excluded_non_us' => count($this->excludedNonUs),
        ];

        if ($jobs === []) {
            $payload['message'] = self::NO_RESULTS_MESSAGE;
        }

        if ($unrecognized !== []) {
            $payload['unrecognized_job_types'] = array_values($unrecognized);
            $payload['notice'] = sprintf(
                'The tool met job types it does not recognize: %s. Tell the user, and ask for the '
                .'search-insight-global-jobs tool to be updated to handle them.',
                implode(', ', array_map(static fn (string $type): string => '"'.$type.'"', array_keys($unrecognized))),
            );
        }

        $payload['jobs'] = $jobs;

        return new SearchOutcome($payload, $this->successfulSearches === 0);
    }

    private function searchKeyword(InsightGlobalSearchCriteria $criteria, string $keyword): void
    {
        $windowStart = $criteria->windowStart();
        $windowEnd = $criteria->windowEnd();
        $page = 1;

        while (true) {
            $result = $this->client->search($criteria->queryPairs($keyword, $page));

            if ($result->budgetExhausted) {
                $this->skipped[] = "keyword {$keyword} page {$page}";

                return;
            }

            if ($result->failed()) {
                $this->errors[] = ['keyword' => $keyword, 'page' => $page, ...$result->errorDetail()];

                return;
            }

            $this->successfulSearches++;

            $jobs = array_values(array_filter($result->data['jobs'], 'is_array'));

            if ($jobs === []) {
                return;
            }

            $oldest = null;

            foreach ($jobs as $entry) {
                $posted = InsightGlobalJobNormalizer::parsePostedDate($entry['postedDate'] ?? null);
                $id = is_string($entry['requisitionId'] ?? null) ? trim($entry['requisitionId']) : '';

                if ($posted === null || $id === '') {
                    continue;
                }

                $oldest = $oldest === null || $posted < $oldest ? $posted : $oldest;

                if ($posted < $windowStart || $posted > $windowEnd) {
                    continue;
                }

                if ($criteria->usOnly && ! $this->normalizer->isUsJob($entry)) {
                    $this->excludedNonUs[$id] = true;

                    continue;
                }

                $this->found[$id] ??= ['entry' => $entry, 'posted' => $posted, 'keywords' => []];

                if (! in_array($keyword, $this->found[$id]['keywords'], true)) {
                    $this->found[$id]['keywords'][] = $keyword;
                }
            }

            if ($criteria->sortsNewestFirst() && $oldest !== null && $oldest < $windowStart) {
                return;
            }

            $totalPages = (int) ($result->data['pageMetadata']['totalPages'] ?? $page);

            if ($page >= $totalPages) {
                return;
            }

            $page++;
        }
    }

    /**
     * @param  array{entry: array<string, mixed>, posted: CarbonImmutable, keywords: list<string>}  $hit
     * @return array<string, mixed>
     */
    private function buildJob(InsightGlobalSearchCriteria $criteria, array $hit): array
    {
        $id = trim((string) $hit['entry']['requisitionId']);

        if (! $criteria->includeDetails) {
            return $this->normalizer->normalize($hit['entry'], null, $hit['keywords']);
        }

        $details = $this->client->details($id);

        if ($details->budgetExhausted) {
            $this->skipped[] = "details {$id}";

            return $this->normalizer->normalize($hit['entry'], null, $hit['keywords'], $details->reason);
        }

        if ($details->failed()) {
            $this->errors[] = ['requisition_id' => $id, ...$details->errorDetail()];

            return $this->normalizer->normalize($hit['entry'], null, $hit['keywords'], $details->reason);
        }

        return $this->normalizer->normalize($hit['entry'], $details->data, $hit['keywords']);
    }
}
