<?php

namespace App\Services\Mcp\Tools\Concerns;

use App\Contracts\ResumeDataServiceContract;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Services\ResumeEditCandidateService;

/**
 * Shared resume-loading logic for MCP tools that need to report whether an
 * AI-drafted revision is pending, and optionally load a specific one, instead
 * of duplicating this across every tool that reads the main resume.
 *
 * Consuming classes must expose a `$context` property holding the caller's
 * {@see \Jvjvjv\CodeTalker\Support\ToolContext}; the draft gate below reads it.
 * Both current consumers already do, via their tool base classes.
 */
trait LoadsResumeDataWithRevisionInfo
{
    /**
     * Load the resume data a tool should return: either the live resume, or
     * a specific candidate revision's snapshot when `$requestedRevisionNumber`
     * is given, found, and the caller is entitled to see drafts.
     *
     * A pending revision is unpublished work. Callers without `edit-resume`
     * are told nothing about it — not that one exists, and not whether a given
     * revision number resolves to anything. Their response is the live resume,
     * byte-identical whether or not they asked for a revision, so that the
     * absence of a draft cannot be distinguished from a refusal to describe it.
     *
     * @return array<string, mixed>
     */
    protected function loadResumeDataWithRevisionInfo(
        ResumeDataServiceContract $resumeDataService,
        ResumeEditCandidateService $candidateService,
        ?int $requestedRevisionNumber = null,
    ): array {
        $liveVersion = ResumeVersion::current()->first();
        $mayViewDrafts = $this->mayViewPendingRevisions();

        $pendingCandidate = ($mayViewDrafts && $liveVersion !== null)
            ? $candidateService->latestPendingCandidateFor($liveVersion)
            : null;

        $requestedCandidate = null;

        if ($mayViewDrafts && $requestedRevisionNumber !== null && $liveVersion !== null) {
            $requestedCandidate = $candidateService->findCandidateByRevisionNumber($liveVersion, $requestedRevisionNumber);
        }

        $data = $requestedCandidate?->snapshot ?? $resumeDataService->getAllEditableData();

        $data['resume_version'] = $liveVersion?->version;

        if (! $mayViewDrafts) {
            return $data;
        }

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

    /**
     * Whether this caller may be told about unpublished draft revisions.
     *
     * The same permission that gates editing and approving drafts: someone who
     * may not change a draft has no business reading one either.
     */
    protected function mayViewPendingRevisions(): bool
    {
        if ($this->context->userId === null) {
            return false;
        }

        return User::find($this->context->userId)?->can('edit-resume') ?? false;
    }
}
