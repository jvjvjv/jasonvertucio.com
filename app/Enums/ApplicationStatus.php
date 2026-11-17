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
    case Ghosted = 'ghosted';
    case Accepted = 'accepted';
    case Hired = 'hired';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

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
            self::Accepted, self::Hired, self::Rejected, self::Withdrawn => true,
            default => false,
        };
    }

    /**
     * Whether an application in this status may get a follow-up email: one
     * waiting on the employer. Not while an interview is in progress, not
     * once ghosted (the follow-ups were already ignored), and not once the
     * process is over. Deliberately has no default arm, so a new status must
     * be classified here.
     */
    public function receivesFollowUps(): bool
    {
        return match ($this) {
            self::Applied, self::Interviewed, self::Offered => true,
            self::Draft, self::Passed, self::Interviewing, self::Ghosted,
            self::Accepted, self::Hired, self::Rejected, self::Withdrawn => false,
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft, self::Passed => [self::Applied],
            self::Applied => [self::Interviewing, self::Offered, self::Ghosted, self::Rejected, self::Withdrawn],
            self::Interviewing => [self::Interviewed, self::Rejected, self::Withdrawn],
            self::Interviewed => [self::Interviewing, self::Offered, self::Ghosted, self::Rejected, self::Withdrawn],
            self::Ghosted => [self::Interviewing, self::Offered, self::Rejected, self::Withdrawn],
            self::Offered => [self::Accepted, self::Hired, self::Rejected, self::Withdrawn],
            default => [],
        };
    }
}
