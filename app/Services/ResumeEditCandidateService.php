<?php

namespace App\Services;

use App\Contracts\ResumeDataServiceContract;
use App\Contracts\ResumeVersionServiceContract;
use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use Illuminate\Support\Facades\DB;
use Jvjvjv\CodeTalker\Models\AiConversation;
use RuntimeException;

class ResumeEditCandidateService
{
    public function __construct(
        protected ResumeDataServiceContract $dataService,
        protected ResumeVersionServiceContract $versionService,
    ) {}

    /**
     * Resolve the candidate a persona edit should be applied to for the given
     * base version: the highest-revision `pending` candidate if its last
     * edit is within the batch window, otherwise a freshly branched one.
     */
    public function resolveOrCreateCandidateForEdit(ResumeVersion $base, ?AiConversation $conversation): ResumeEditCandidate
    {
        $latestPending = $this->latestPendingCandidateFor($base);

        $windowHours = (int) config('resume.ai_edit_batch_window_hours', 12);

        if ($latestPending !== null && $latestPending->last_edited_at->addHours($windowHours)->isFuture()) {
            return $latestPending;
        }

        $nextRevisionNumber = ResumeEditCandidate::query()
            ->where('base_resume_version_id', $base->id)
            ->max('revision_number') + 1;

        $seedSnapshot = $latestPending?->snapshot ?? $this->dataService->getAllEditableData();

        $now = now();

        return ResumeEditCandidate::create([
            'base_resume_version_id' => $base->id,
            'revision_number' => $nextRevisionNumber,
            'status' => 'pending',
            'snapshot' => $seedSnapshot,
            'ai_conversation_id' => $conversation?->id,
            'batch_started_at' => $now,
            'last_edited_at' => $now,
        ]);
    }

    /**
     * Apply a partial edit to one section of a candidate's snapshot.
     *
     * @param  array<string, mixed>  $sectionData
     */
    public function applySectionEdit(ResumeEditCandidate $candidate, string $section, array $sectionData): ResumeEditCandidate
    {
        $snapshot = $candidate->snapshot;
        $snapshot[$section] = $sectionData;

        $candidate->update([
            'snapshot' => $snapshot,
            'last_edited_at' => now(),
        ]);

        return $candidate->fresh();
    }

    /**
     * Approve a pending candidate: materialize its snapshot as the new live
     * resume version, regenerate documents, mark it approved, and permanently
     * reject every other pending candidate branched from the same base
     * version (they were seeded from data this approval has now superseded).
     *
     * @return array{success: bool, error?: string}
     */
    public function approve(ResumeEditCandidate $candidate, string $approvedByUserId): array
    {
        if ($candidate->status !== 'pending') {
            throw new RuntimeException('Only a pending candidate can be approved.');
        }

        $nextVersion = $this->nextPatchVersion($candidate->baseResumeVersion->version);

        DB::transaction(function () use ($candidate, $nextVersion, $approvedByUserId) {
            $this->versionService->setVersion($nextVersion);
            $this->dataService->saveAllEditableData($candidate->snapshot);

            $candidate->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by_user_id' => $approvedByUserId,
            ]);

            ResumeEditCandidate::query()
                ->where('base_resume_version_id', $candidate->base_resume_version_id)
                ->where('id', '!=', $candidate->id)
                ->pending()
                ->delete();
        });

        $docxResult = $this->versionService->generateDocx();
        $pdfResult = $docxResult['success'] ? $this->versionService->generatePdf() : ['success' => false];

        if (! $docxResult['success']) {
            return ['success' => true, 'error' => 'DOCX generation failed: '.($docxResult['error'] ?? 'Unknown error')];
        }

        if (! $pdfResult['success']) {
            return ['success' => true, 'error' => 'PDF generation failed: '.($pdfResult['error'] ?? 'Unknown error')];
        }

        return ['success' => true];
    }

    /**
     * Reject a pending candidate: permanently delete it. No undo.
     */
    public function reject(ResumeEditCandidate $candidate): void
    {
        if ($candidate->status !== 'pending') {
            throw new RuntimeException('Only a pending candidate can be rejected.');
        }

        $candidate->delete();
    }

    /**
     * Whether any pending candidate exists for the given base version,
     * blocking manual edits to it until resolved.
     */
    public function hasPendingCandidateFor(ResumeVersion $base): bool
    {
        return ResumeEditCandidate::query()
            ->where('base_resume_version_id', $base->id)
            ->pending()
            ->exists();
    }

    /**
     * The candidate new persona edits would attach to for this base version:
     * the highest-revision `pending` candidate, or null if none exists.
     */
    public function latestPendingCandidateFor(ResumeVersion $base): ?ResumeEditCandidate
    {
        return ResumeEditCandidate::query()
            ->where('base_resume_version_id', $base->id)
            ->pending()
            ->orderByDesc('revision_number')
            ->first();
    }

    /**
     * Look up a specific candidate revision for a base version, regardless of
     * status (used to let a tool caller inspect a particular draft by number).
     */
    public function findCandidateByRevisionNumber(ResumeVersion $base, int $revisionNumber): ?ResumeEditCandidate
    {
        return ResumeEditCandidate::query()
            ->where('base_resume_version_id', $base->id)
            ->where('revision_number', $revisionNumber)
            ->first();
    }

    /**
     * Bump the patch segment of a `YYYY.MAJOR.MINOR` version string.
     */
    private function nextPatchVersion(string $version): string
    {
        [$year, $major, $minor] = array_map('intval', explode('.', $version));

        return sprintf('%d.%d.%d', $year, $major, $minor + 1);
    }
}
