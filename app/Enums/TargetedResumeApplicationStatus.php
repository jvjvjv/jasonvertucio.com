<?php

namespace App\Enums;

enum TargetedResumeApplicationStatus: string {
    case Applied = 'applied';
    case Interviewing = 'interviewing';
    case Interviewed = 'interviewed';
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Hired = 'hired';
    case Rejected = 'rejected';

    public function isTerminal(): bool {
        return match ($this) {
            self::Accepted, self::Hired, self::Rejected => true,
            default => false,
        };
    }

    /**
     * @return array<int, self>
     */
    public function allowedNext(): array {
        return match ($this) {
            self::Applied => [self::Interviewing, self::Offered, self::Rejected],
            self::Interviewing => [self::Interviewed, self::Rejected],
            self::Interviewed => [self::Interviewing, self::Offered, self::Rejected],
            self::Offered => [self::Accepted, self::Hired, self::Rejected],
            default => [],
        };
    }
}
