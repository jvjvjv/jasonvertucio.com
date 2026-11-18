<?php

namespace App\Services\JobSearch\InsightGlobal;

use App\Enums\InsightGlobalJobType;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Turns an Insight Global search entry (and, when fetched, its details body)
 * into the tool's normalized job shape.
 *
 * Every value comes from the API responses. A field the API does not supply is
 * left out, never inferred.
 */
class InsightGlobalJobNormalizer
{
    public const COMPANY = 'Insight Global';

    public const JOB_URL = 'https://insightglobal.com/jobs/%s';

    /**
     * The 50 states, DC and Puerto Rico.
     */
    private const US_STATE_CODES = [
        'AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA',
        'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD',
        'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ',
        'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC',
        'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY',
        'DC', 'PR',
    ];

    /**
     * Description headings, in the order they normally appear, mapped to the
     * output key their section is returned under.
     */
    private const SECTION_HEADINGS = [
        'required skills & experience' => 'required_skills',
        'nice to have skills & experience' => 'preferred_skills',
        'job description' => 'job_description',
    ];

    /**
     * @param  array<string, mixed>  $entry  One element of the search response's `jobs` array
     * @param  array<string, mixed>|null  $details  The details response body, or null when not fetched
     * @param  list<string>  $matchedKeywords
     * @return array<string, mixed>
     */
    public function normalize(
        array $entry,
        ?array $details = null,
        array $matchedKeywords = [],
        ?string $detailsError = null,
    ): array {
        $requisitionId = $this->stringOrNull($entry['requisitionId'] ?? null);
        $rawJobType = $this->stringOrNull($entry['jobType'] ?? null);
        $posted = self::parsePostedDate($entry['postedDate'] ?? null);
        $remote = is_bool($entry['workRemote'] ?? null) ? $entry['workRemote'] : null;
        $payRate = is_array($entry['payRate'] ?? null) ? $entry['payRate'] : null;

        $job = [
            'job_id' => $requisitionId,
            'title' => $this->stringOrNull($entry['jobTitle'] ?? null),
            'company' => self::COMPANY,
            'location' => $this->location($entry['workAddress'] ?? null),
            'remote_status' => match ($remote) {
                true => 'Remote',
                false => 'On-site or hybrid',
                null => null,
            },
            'remote' => $remote,
            'employment_type' => InsightGlobalJobType::fromRaw($rawJobType)?->employmentType(),
            'job_type' => $rawJobType,
            'posted_date' => $posted?->format('n/j/Y'),
            'posted_at' => $posted?->toIso8601String(),
            'salary' => $payRate === null ? null : $this->salary($payRate),
            'pay_rate' => $payRate,
            'job_url' => $requisitionId === null ? null : sprintf(self::JOB_URL, $requisitionId),
            'matched_keywords' => $matchedKeywords === [] ? null : $matchedKeywords,
        ];

        if ($details !== null) {
            $job = [...$job, ...$this->descriptionSections($details['description'] ?? null)];
        }

        $job['details_error'] = $detailsError;

        return array_filter($job, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Whether the entry carries a raw job type the tool does not recognize. A
     * missing job type is not an unrecognized one.
     *
     * @param  array<string, mixed>  $entry
     */
    public function hasUnrecognizedJobType(array $entry): bool
    {
        $raw = $this->stringOrNull($entry['jobType'] ?? null);

        return $raw !== null && InsightGlobalJobType::fromRaw($raw) === null;
    }

    /**
     * Whether the entry is shown to be a US job: its listed state is a US state,
     * DC or Puerto Rico, and its title has no "INTL" (international roles are
     * sometimes listed at a US address). A missing state is not shown to be US.
     *
     * @param  array<string, mixed>  $entry
     */
    public function isUsJob(array $entry): bool
    {
        $state = $this->stringOrNull($entry['workAddress']['administrativeArea'] ?? null);

        if ($state === null || ! in_array(strtoupper($state), self::US_STATE_CODES, true)) {
            return false;
        }

        return preg_match('/\bINTL\b/i', (string) ($entry['jobTitle'] ?? '')) !== 1;
    }

    public static function parsePostedDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Convert the details HTML to plain text and split it on the known headings.
     *
     * @return array{required_skills?: string, preferred_skills?: string, job_description?: string}
     */
    public function descriptionSections(mixed $html): array
    {
        if (! is_string($html)) {
            return [];
        }

        $text = $this->plainText($html);

        if ($text === '') {
            return [];
        }

        $sections = [];
        $current = null;
        $preamble = [];

        foreach (explode("\n", $text) as $line) {
            $heading = mb_strtolower(trim($line));

            if (isset(self::SECTION_HEADINGS[$heading])) {
                $current = self::SECTION_HEADINGS[$heading];
                $sections[$current] ??= [];

                continue;
            }

            if ($current === null) {
                $preamble[] = $line;
            } else {
                $sections[$current][] = $line;
            }
        }

        if ($sections === []) {
            return ['job_description' => $text];
        }

        if (trim(implode("\n", $preamble)) !== '') {
            $sections['job_description'] = [...$preamble, '', ...($sections['job_description'] ?? [])];
        }

        $result = [];

        foreach (self::SECTION_HEADINGS as $key) {
            $body = trim(implode("\n", $sections[$key] ?? []));

            if ($body !== '') {
                $result[$key] = $body;
            }
        }

        return $result;
    }

    private function plainText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>|<\/(p|li|div|h[1-6])>/i', "\n", $html) ?? $html;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[ \t]+$/m', '', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function location(mixed $address): ?string
    {
        if (! is_array($address)) {
            return null;
        }

        $parts = array_filter([
            $this->stringOrNull($address['locality'] ?? null),
            $this->stringOrNull($address['administrativeArea'] ?? null),
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $payRate
     */
    private function salary(array $payRate): ?string
    {
        $min = is_numeric($payRate['min'] ?? null) ? (float) $payRate['min'] : null;
        $max = is_numeric($payRate['max'] ?? null) ? (float) $payRate['max'] : null;

        if ($min === null && $max === null) {
            return null;
        }

        $currency = $this->stringOrNull($payRate['currency'] ?? null);

        $amount = match (true) {
            $min !== null && $max !== null && $min !== $max => $this->money($min, $currency).'–'.$this->money($max, $currency),
            $min !== null => $this->money($min, $currency),
            default => 'Up to '.$this->money((float) $max, $currency),
        };

        if ($currency !== null && strtoupper($currency) !== 'USD') {
            $amount .= ' '.strtoupper($currency);
        }

        return $amount.match ($this->stringOrNull($payRate['type'] ?? null)) {
            'Hourly' => '/hr',
            'Annually' => '/yr',
            null => '',
            default => ' ('.$payRate['type'].')',
        };
    }

    private function money(float $value, ?string $currency): string
    {
        $formatted = floor($value) === $value
            ? number_format($value)
            : number_format($value, 2);

        return $currency !== null && strtoupper($currency) === 'USD' ? '$'.$formatted : $formatted;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
