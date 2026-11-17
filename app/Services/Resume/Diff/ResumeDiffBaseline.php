<?php

namespace App\Services\Resume\Diff;

use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use App\Services\DatabaseResumeDataService;

/**
 * Chooses what a draft revision is compared with:
 *
 * - `base`, always offered: the version it branched from;
 * - `live`, offered when the draft is stale: the current version;
 * - `previous`, offered when one exists: the nearest earlier revision of the
 *   same base version.
 *
 * Baselines are database versions by definition, so this reads them through
 * the database service rather than the configured driver.
 */
class ResumeDiffBaseline
{
    public function __construct(private DatabaseResumeDataService $resumeData) {}

    /**
     * @param  ResumeVersion|null|false  $live  the current version when the caller already has it; false to look it up
     */
    public function resolve(ResumeEditCandidate $candidate, ?string $requested, ResumeVersion|null|false $live = false): ResumeBaseline
    {
        $base = $candidate->baseResumeVersion;
        $live = $live === false ? ResumeVersion::current()->first() : $live;
        $previous = ResumeEditCandidate::query()
            ->where('base_resume_version_id', $candidate->base_resume_version_id)
            ->where('revision_number', '<', $candidate->revision_number)
            ->orderByDesc('revision_number')
            ->first();

        $available = [ResumeBaseline::BASE => $base !== null ? "Version it branched from ({$base->version})" : 'Version it branched from'];

        if ($live !== null && $live->id !== $candidate->base_resume_version_id) {
            $available[ResumeBaseline::LIVE] = "Live resume ({$live->version})";
        }

        if ($previous !== null) {
            $available[ResumeBaseline::PREVIOUS] = "Previous revision (#{$previous->revision_number})";
        }

        $isRequestedAvailable = $requested !== null && $requested !== '' && isset($available[$requested]);
        $mode = $isRequestedAvailable ? $requested : ResumeBaseline::BASE;

        [$label, $data] = match ($mode) {
            ResumeBaseline::LIVE => ["the live resume, version {$live->version}", $this->resumeData->getAllEditableData($live)],
            ResumeBaseline::PREVIOUS => ["revision #{$previous->revision_number}", is_array($previous->snapshot) ? $previous->snapshot : []],
            default => $base !== null
                ? ["version {$base->version}, the one it branched from", $this->resumeData->getAllEditableData($base)]
                : ['an empty resume, because the version it branched from no longer exists', []],
        };

        return new ResumeBaseline(
            mode: $mode,
            label: $label,
            data: $data,
            available: $available,
            fellBack: $requested !== null && $requested !== '' && ! $isRequestedAvailable,
        );
    }
}
