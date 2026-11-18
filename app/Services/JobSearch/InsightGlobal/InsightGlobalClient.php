<?php

namespace App\Services\JobSearch\InsightGlobal;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use JsonException;

/**
 * Sends the two Insight Global JSON requests the search tool needs.
 *
 * The host and paths are fixed, so there is no model-chosen URL to police.
 * Redirects are not followed; a 3xx counts as a failure. Every request goes
 * through the {@see RequestBudget}, which spaces and caps them.
 */
class InsightGlobalClient
{
    public const SEARCH_URL = 'https://insightglobal.com/all/jobs';

    public const DETAILS_URL = 'https://insightglobal.com/all/jobs/%s/details';

    public function __construct(
        private readonly RequestBudget $budget,
        private readonly string $userAgent,
    ) {}

    /**
     * @param  list<array{0: string, 1: string|null}>  $pairs  Query parameters in order; a null value is left out
     */
    public function search(array $pairs): ClientResult
    {
        $result = $this->get(self::SEARCH_URL.'?'.self::queryString($pairs));

        if (! $result->failed() && ! is_array($result->data['jobs'] ?? null)) {
            return ClientResult::failure('the response could not be parsed: it has no jobs array');
        }

        return $result;
    }

    public function details(string $requisitionId): ClientResult
    {
        return $this->get(sprintf(self::DETAILS_URL, rawurlencode($requisitionId)));
    }

    /**
     * Build a query string with bare repeated keys (`sort=a&sort=b`), which the
     * API expects and which Laravel's and PHP's array serializers do not produce.
     *
     * @param  list<array{0: string, 1: string|null}>  $pairs
     */
    public static function queryString(array $pairs): string
    {
        $encoded = [];

        foreach ($pairs as [$name, $value]) {
            if ($value === null) {
                continue;
            }

            $encoded[] = rawurlencode($name).'='.rawurlencode($value);
        }

        return implode('&', $encoded);
    }

    private function get(string $url): ClientResult
    {
        if (! $this->budget->acquire()) {
            return ClientResult::budgetExhausted();
        }

        try {
            $response = Http::accept('application/json')
                ->withUserAgent($this->userAgent)
                ->withoutRedirecting()
                ->connectTimeout(RequestBudget::CONNECT_TIMEOUT_SECONDS)
                ->timeout(RequestBudget::REQUEST_TIMEOUT_SECONDS)
                ->get($url);
        } catch (ConnectionException $exception) {
            return ClientResult::failure('the connection failed: '.$exception->getMessage());
        }

        if ($response->redirect()) {
            return ClientResult::failure('the response was a redirect, which is not followed', $response->status());
        }

        if (! $response->successful()) {
            return ClientResult::failure('HTTP '.$response->status(), $response->status());
        }

        try {
            $decoded = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ClientResult::failure('the response could not be parsed as JSON', $response->status());
        }

        if (! is_array($decoded)) {
            return ClientResult::failure('the response could not be parsed as a JSON object', $response->status());
        }

        return ClientResult::ok($decoded);
    }
}
