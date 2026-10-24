<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ApplicationStatusResolver
{
    /**
     * The display status used when an application has been applied but has
     * received no further update within the configured threshold.
     */
    public const GHOSTED = 'ghosted';

    /**
     * Resolve the display status for an application.
     *
     * Mirrors the client-side display-status resolver so the backend and
     * frontend agree on when an application is "ghosted". The stored status
     * is never changed by this.
     */
    public static function resolve(
        ?string $status,
        ?CarbonInterface $latestStatusOccurredAt,
        ?int $ghostedAfterDays = null,
    ): string {
        $threshold = $ghostedAfterDays ?? (int) config('resume.ghosted_after_days');

        if (
            $status === 'applied'
            && $latestStatusOccurredAt !== null
            && $latestStatusOccurredAt->isBefore(Carbon::now()->subDays($threshold))
        ) {
            return self::GHOSTED;
        }

        return $status ?? '';
    }
}
