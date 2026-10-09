<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTargetedResumeMarkdownRequest;
use App\Models\TargetedResume;
use App\Services\ApplicationService;
use App\Services\DocumentDownloadLogger;
use App\Services\TargetedResumeDocumentService;
use App\Services\TargetedResumeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The targeted resume documents themselves: listing, editing, downloading
 * and discarding them. The jobs they were written for are applications.
 */
class TargetedResumeController extends Controller
{
    public function __construct(
        private TargetedResumeService $targetedResumeService,
        private DocumentDownloadLogger $downloadLogger,
    ) {}

    /**
     * List the targeted resumes of non-deleted applications, most recently
     * edited first, with an optional search.
     */
    public function index(Request $request): InertiaResponse
    {
        $search = trim((string) $request->string('search'));

        $targetedResumes = TargetedResume::query()
            ->whereHas('application')
            ->with(['resumeVersion:id,version', 'application' => $this->withAppliedFlag(...)])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $matching) use ($search): void {
                $matching->where('title', 'LIKE', '%'.$search.'%')
                    ->orWhereHas('application', function (Builder $application) use ($search): void {
                        $application->where('company_name', 'LIKE', '%'.$search.'%')
                            ->orWhere('position', 'LIKE', '%'.$search.'%');
                    });
            }))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (TargetedResume $targetedResume): array => [
                'id' => $targetedResume->id,
                'title' => $targetedResume->title,
                'application_id' => $targetedResume->application->id,
                'company_name' => $targetedResume->application->company_name,
                'position' => $targetedResume->application->position,
                'resume_version' => $targetedResume->resumeVersion?->version,
                'updated_at' => $targetedResume->updated_at?->toIso8601String(),
                'updated_at_human' => $targetedResume->updated_at?->diffForHumans(),
                'can_discard' => ! $targetedResume->application->has_applied,
            ]);

        return Inertia::render('resume/targeted/Index', [
            'targetedResumes' => $targetedResumes,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the editor for a targeted resume's content.
     */
    public function edit(TargetedResume $targetedResume): InertiaResponse
    {
        $targetedResume->load(['resumeVersion:id,version', 'application' => $this->withAppliedFlag(...)]);

        $application = $targetedResume->application;

        abort_if($application === null, 404);

        return Inertia::render('resume/targeted/Edit', [
            'targetedResume' => [
                'id' => $targetedResume->id,
                'title' => $targetedResume->title,
                'tailored_content' => data_get($targetedResume->tailored_data, 'markdown')
                    ?? data_get($targetedResume->tailored_data, 'content'),
                'resume_version' => $targetedResume->resumeVersion?->version,
                'docx_path' => (bool) $targetedResume->docx_path,
                'pdf_path' => (bool) $targetedResume->pdf_path,
                'can_discard' => ! $application->has_applied,
            ],
            'application' => [
                'id' => $application->id,
                'company_name' => $application->company_name,
                'position' => $application->position,
            ],
        ]);
    }

    /**
     * Persist a manually edited targeted resume markdown and invalidate its
     * rendered documents.
     */
    public function updateMarkdown(UpdateTargetedResumeMarkdownRequest $request, TargetedResume $targetedResume): JsonResponse
    {
        $result = $this->targetedResumeService->updateTailoredMarkdown(
            $targetedResume,
            $request->validated('markdown'),
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'targeted_resume_id' => $result['targetedResume']->id,
            'message' => 'Targeted resume updated successfully.',
        ]);
    }

    /**
     * Download a targeted resume document, generating it first if missing or stale.
     */
    public function download(Request $request, TargetedResume $targetedResume, string $format, TargetedResumeDocumentService $documentService): BinaryFileResponse
    {
        $result = match ($format) {
            'docx' => $documentService->ensureDocx($targetedResume),
            'pdf' => $documentService->ensurePdf($targetedResume),
            default => abort(404),
        };

        if (! $result['success']) {
            abort(404, $result['error'] ?? 'Document could not be generated.');
        }

        $this->downloadLogger->log($targetedResume, $format, $result['served_cached_document'], $request->ip() ?? '');

        $deleteAfterServe = config('resume.document_retention_mode') === 'delete_after_serve';

        if ($deleteAfterServe) {
            $column = $format === 'docx' ? 'docx_path' : 'pdf_path';
            $targetedResume->forceFill([$column => null])->save();
        }

        $filename = $targetedResume->generateFilename().'.'.$format;

        $response = response()->download($result['path'], $filename);

        if ($deleteAfterServe) {
            $response->deleteFileAfterSend(true);
        }

        return $response;
    }

    /**
     * Invalidate any previously rendered documents for a targeted resume so
     * the next download renders fresh.
     */
    public function regenerate(TargetedResume $targetedResume): RedirectResponse
    {
        $targetedResume->invalidateDocuments();

        $application = $targetedResume->application;

        return ($application !== null
            ? redirect()->route('admin.resume.applications.show', $application)
            : redirect()->route('admin.resume.targeted.index'))
            ->with('success', 'Cached documents cleared. They will be regenerated on the next download.');
    }

    /**
     * Permanently discard a targeted resume. Refused once its application
     * has been applied to.
     */
    public function destroy(TargetedResume $targetedResume, ApplicationService $applicationService): RedirectResponse
    {
        $application = $applicationService->discardTargetedResume($targetedResume);

        return ($application !== null
            ? redirect()->route('admin.resume.applications.show', $application)
            : redirect()->route('admin.resume.targeted.index'))
            ->with('success', 'Targeted resume discarded.');
    }

    /**
     * Eager-load constraint that answers "has this application been applied
     * to?" as a `has_applied` attribute, so a list never asks per row.
     */
    private function withAppliedFlag(mixed $application): void
    {
        $application->withExists([
            'statusUpdates as has_applied' => fn ($statusUpdates) => $statusUpdates->where('status', ApplicationStatus::Applied->value),
        ]);
    }
}
