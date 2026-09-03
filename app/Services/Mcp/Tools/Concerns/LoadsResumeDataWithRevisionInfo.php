<?php

namespace App\Services\Mcp\Tools\Concerns;

use App\Contracts\ResumeDataServiceContract;
use App\Models\ResumeVersion;
use App\Services\ResumeEditCandidateService;

/**
 * Shared resume-loading logic for MCP tools that need to report whether an
 * AI-drafted revision is pending, and optionally load a specific one, instead
 * of duplicating this across every tool that reads the main resume.
 */
trait LoadsResumeDataWithRevisionInfo
{
    /**
     * Load the resume data a tool should return: either the live resume, or
     * a specific candidate revision's snapshot when `$requestedRevisionNumber`
     * is given and found. Always reports `resume_version` and
     * `pending_revision_number` so the caller can tell the user a draft is
     * already in progress, whether or not they asked to view it.
     *
     * @return array<string, mixed>
     */
    protected function loadResumeDataWithRevisionInfo(
        ResumeDataServiceContract $resumeDataService,
        ResumeEditCandidateService $candidateService,
        ?int $requestedRevisionNumber = null,
    ): array {
        $liveVersion = ResumeVersion::current()->first();
        $pendingCandidate = $liveVersion ? $candidateService->latestPendingCandidateFor($liveVersion) : null;

        $requestedCandidate = null;

        if ($requestedRevisionNumber !== null && $liveVersion !== null) {
            $requestedCandidate = $candidateService->findCandidateByRevisionNumber($liveVersion, $requestedRevisionNumber);
        }

        $data = $requestedCandidate?->snapshot ?? $resumeDataService->getAllEditableData();

        $data['resume_version'] = $liveVersion?->version;
        $data['pending_revision_number'] = $pendingCandidate?->revision_number;

        if ($requestedRevisionNumber !== null) {
            $data['requested_revision_found'] = $requestedCandidate !== null;

            if ($requestedCandidate !== null) {
                $data['viewing_revision_number'] = $requestedCandidate->revision_number;
                $data['viewing_revision_status'] = $requestedCandidate->status;
            }
        }

        return $data;
    }
}
