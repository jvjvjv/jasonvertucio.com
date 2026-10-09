<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Passed = 'passed';
    case Applied = 'applied';
    case Interviewing = 'interviewing';
    case Interviewed = 'interviewed';
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Hired = 'hired';
    case Rejected = 'rejected';

    /**
     * The statuses an application moves through once it has been applied to —
     * the only ones a status-history entry may carry.
     *
     * @return array<int, self>
     */
    public static function pipeline(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status->isPipeline()));
    }

    public function isPipeline(): bool
    {
        return match ($this) {
            self::Draft, self::Passed => false,
            default => true,
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Accepted, self::Hired, self::Rejected => true,
            default => false,
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft, self::Passed => [self::Applied],
            self::Applied => [self::Interviewing, self::Offered, self::Rejected],
            self::Interviewing => [self::Interviewed, self::Rejected],
            self::Interviewed => [self::Interviewing, self::Offered, self::Rejected],
            self::Offered => [self::Accepted, self::Hired, self::Rejected],
            default => [],
        };
    }
}
