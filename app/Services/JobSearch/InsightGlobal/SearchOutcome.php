<?php

namespace App\Services\JobSearch\InsightGlobal;

/**
 * What one search run produced: the structured payload, and whether every
 * search request failed (which the tool reports as an error response).
 */
final readonly class SearchOutcome
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public array $payload,
        public bool $everySearchFailed,
    ) {}
}
