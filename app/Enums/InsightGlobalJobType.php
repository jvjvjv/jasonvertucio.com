<?php

namespace App\Enums;

/**
 * The Insight Global job types the search tool understands.
 *
 * The single source for three things that must not drift apart: the values the
 * tool accepts as `job_types` input (the case values), the spelling sent as
 * `filter.jobType` ({@see filterValue()}), and the raw `jobType` values a result
 * is recognized by ({@see fromRaw()}). A raw value that matches no case is
 * reported back to the user for a tool update rather than guessed at.
 */
enum InsightGlobalJobType: string
{
    case Contract = 'Contract';
    case Perm = 'Perm';
    case ContractToPerm = 'Contract To Perm';

    /**
     * The value the search endpoint expects. `Contract To Perm` itself answers
     * HTTP 500; the endpoint wants it without spaces.
     */
    public function filterValue(): string
    {
        return match ($this) {
            self::Contract => 'Contract',
            self::Perm => 'Perm',
            self::ContractToPerm => 'ContractToPerm',
        };
    }

    public function employmentType(): string
    {
        return match ($this) {
            self::Contract => 'Contract',
            self::Perm => 'Full Time',
            self::ContractToPerm => 'Contract to Hire',
        };
    }

    /**
     * Match a raw `jobType` from a search result exactly, ignoring case and
     * surrounding whitespace. No prefix or fuzzy matching.
     */
    public static function fromRaw(?string $raw): ?self
    {
        if ($raw === null) {
            return null;
        }

        $needle = mb_strtolower(trim($raw));

        foreach (self::cases() as $case) {
            if (mb_strtolower($case->value) === $needle) {
                return $case;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
