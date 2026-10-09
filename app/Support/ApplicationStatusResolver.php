<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class TargetedResumeStatusResolver
{
    /**
     * The display status used when an application has been applied but has
     * received no further update within the configured threshold.
     */
    public const GHOSTED = 'ghosted';

    /**
     * Resolve the display status for a targeted resume.
     *
     * Mirrors the client-side resolveTargetedResumeDisplayStatus so the
     * backend and frontend agree on when an application is "ghosted".
     */
    public static function resolve(
        ?string $resumeStatus,
        ?CarbonInterface $latestStatusOccurredAt,
        ?int $ghostedAfterDays = null,
        ?string $conversationStatus = null,
    ): string {
        $threshold = $ghostedAfterDays ?? (int) config('resume.ghosted_after_days');

        if (
            $resumeStatus === 'applied'
            && $latestStatusOccurredAt !== null
            && $latestStatusOccurredAt->isBefore(Carbon::now()->subDays($threshold))
        ) {
            return self::GHOSTED;
        }

        if ($resumeStatus !== null && $resumeStatus !== '' && $resumeStatus !== 'draft') {
            return $resumeStatus;
        }

        return $conversationStatus ?? ($resumeStatus ?? '');
    }
}
