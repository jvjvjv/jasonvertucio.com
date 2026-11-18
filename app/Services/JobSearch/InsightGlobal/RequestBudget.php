<?php

namespace App\Services\JobSearch\InsightGlobal;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Sleep;

/**
 * Spaces requests to Insight Global and caps one tool call's request count and
 * elapsed time.
 *
 * A chat turn runs inside the queue worker (`--timeout=299`) under a 300 s turn
 * ceiling that also covers the model's own generation, so a call must finish
 * well inside both. Elapsed time is read from Carbon so tests can drive it with
 * `Sleep::fake(syncWithCarbon: true)`.
 */
class RequestBudget
{
    public const MAX_REQUESTS = 45;

    public const MAX_SECONDS = 120;

    public const SPACING_SECONDS = 2;

    public const REQUEST_TIMEOUT_SECONDS = 15;

    public const CONNECT_TIMEOUT_SECONDS = 10;

    private int $sent = 0;

    private ?CarbonImmutable $startedAt = null;

    public function __construct(
        private readonly int $maxRequests = self::MAX_REQUESTS,
        private readonly int $maxSeconds = self::MAX_SECONDS,
    ) {}

    /**
     * Claim the next request. Sleeps the spacing interval first when a request
     * has already been sent. Returns false, without sleeping, when the request
     * would exceed the count or could not complete inside the time budget.
     */
    public function acquire(): bool
    {
        $this->startedAt ??= CarbonImmutable::instance(Carbon::now());

        if ($this->sent >= $this->maxRequests) {
            return false;
        }

        $spacing = $this->sent > 0 ? self::SPACING_SECONDS : 0;

        if ($this->elapsedSeconds() + $spacing + self::REQUEST_TIMEOUT_SECONDS > $this->maxSeconds) {
            return false;
        }

        if ($spacing > 0) {
            Sleep::for($spacing)->seconds();
        }

        $this->sent++;

        return true;
    }

    public function sent(): int
    {
        return $this->sent;
    }

    private function elapsedSeconds(): float
    {
        return abs($this->startedAt->diffInSeconds(Carbon::now()));
    }
}
