<?php

namespace App\Services\JobSearch\InsightGlobal;

use App\Enums\InsightGlobalJobType;
use Carbon\CarbonImmutable;

/**
 * Validated inputs for one Insight Global search, and the query each keyword
 * and page is requested with.
 */
final readonly class InsightGlobalSearchCriteria
{
    /**
     * Single words only: Insight Global matches a multi-word keyword such as
     * "frontend developer" against almost every developer job.
     */
    public const FALLBACK_KEYWORDS = [
        'vue',
        'react',
        'typescript',
    ];

    public const SORTS = [
        'relevance,desc',
        'postedDate,desc',
        'payRate.max,asc',
        'payRate.min,asc',
    ];

    public const DEFAULT_SORT = 'postedDate,desc';

    public const DEFAULT_DAYS_BACK = 7;

    public const DEFAULT_DISTANCE = 10;

    public const DEFAULT_MAX_RESULTS = 25;

    public const PAGE_SIZE = 10;

    public const STATUS = 'active';

    private const CONTRACT_PAY_FLOOR = 70;

    private const PERM_PAY_FLOOR = 130000;

    /**
     * @param  list<string>  $keywords  Encoded keywords, in search order
     * @param  list<InsightGlobalJobType>  $jobTypes
     * @param  list<string>  $sort
     */
    public function __construct(
        public array $keywords,
        public CarbonImmutable $today,
        public bool $todaySupplied,
        public int $daysBack,
        public bool $daysBackSupplied,
        public array $jobTypes = [],
        public bool $remoteOnly = false,
        public ?float $minimumPayRate = null,
        public ?string $location = null,
        public ?int $distance = null,
        public array $sort = [self::DEFAULT_SORT],
        public bool $includeDetails = true,
        public int $maxResults = self::DEFAULT_MAX_RESULTS,
        public bool $usOnly = true,
    ) {}

    /**
     * Lowercase, trim, and join words with single hyphens: "Twilio Developer"
     * becomes "twilio-developer".
     */
    public static function encodeKeyword(string $keyword): string
    {
        return (string) preg_replace('/\s+/', '-', mb_strtolower(trim($keyword)));
    }

    /**
     * Encode the caller's keywords, dropping blanks and repeats, or fall back to
     * the default list when none remain.
     *
     * @param  list<string>  $keywords
     * @return list<string>
     */
    public static function keywordsFrom(array $keywords): array
    {
        $encoded = array_values(array_unique(array_filter(
            array_map(self::encodeKeyword(...), $keywords),
            static fn (string $keyword): bool => $keyword !== '',
        )));

        return $encoded !== [] ? $encoded : array_map(self::encodeKeyword(...), self::FALLBACK_KEYWORDS);
    }

    public function windowStart(): CarbonImmutable
    {
        return $this->today->subDays($this->daysBack)->startOfDay();
    }

    public function windowEnd(): CarbonImmutable
    {
        return $this->today->endOfDay();
    }

    /**
     * Whether later pages are guaranteed older, so paging may stop at the
     * window edge.
     */
    public function sortsNewestFirst(): bool
    {
        return $this->sort === [self::DEFAULT_SORT];
    }

    public function effectiveMinimumPayRate(): ?float
    {
        if ($this->jobTypes === []) {
            return null;
        }

        if ($this->minimumPayRate !== null) {
            return $this->minimumPayRate;
        }

        return match ($this->jobTypes) {
            [InsightGlobalJobType::Contract] => self::CONTRACT_PAY_FLOOR,
            [InsightGlobalJobType::Perm] => self::PERM_PAY_FLOOR,
            default => null,
        };
    }

    public function effectiveDistance(): ?int
    {
        if ($this->location === null || $this->remoteOnly) {
            return null;
        }

        return max(5, (int) round(($this->distance ?? self::DEFAULT_DISTANCE) / 5) * 5);
    }

    /**
     * @return list<array{0: string, 1: string|null}>
     */
    public function queryPairs(string $keyword, int $page): array
    {
        $minimumPay = $this->effectiveMinimumPayRate();
        $distance = $this->effectiveDistance();

        return [
            ['keyword', $keyword],
            ['page', (string) $page],
            ['size', (string) self::PAGE_SIZE],
            ...array_map(static fn (string $sort): array => ['sort', $sort], $this->sort),
            ...array_map(static fn (InsightGlobalJobType $type): array => ['filter.jobType', $type->filterValue()], $this->jobTypes),
            ['filter.includeOnlyRemoteWork', $this->remoteOnly ? 'true' : null],
            ['filter.minimumPayRateGreaterThan', $minimumPay === null ? null : self::number($minimumPay)],
            ['filter.locations', $this->location],
            ['filter.distance', $distance === null ? null : (string) $distance],
            ['filter.status', self::STATUS],
        ];
    }

    /**
     * The filters actually applied, reported back to the persona.
     *
     * @return array<string, mixed>
     */
    public function filtersSummary(): array
    {
        return [
            'job_types' => array_map(static fn (InsightGlobalJobType $type): string => $type->value, $this->jobTypes),
            'remote_only' => $this->remoteOnly,
            'us_only' => $this->usOnly,
            'minimum_pay_rate' => $this->effectiveMinimumPayRate(),
            'location' => $this->location,
            'distance' => $this->effectiveDistance(),
            'sort' => $this->sort,
            'status' => self::STATUS,
        ];
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
