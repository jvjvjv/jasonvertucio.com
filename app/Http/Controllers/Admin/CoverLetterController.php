<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCoverLetterRequest;
use App\Models\Application;
use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Services\CoverLetterDocumentService;
use App\Services\DocumentDownloadLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CoverLetterController extends Controller
{
    public function __construct(
        protected CoverLetterDocumentService $documentService,
        protected DocumentDownloadLogger $downloadLogger,
    ) {}

    /**
     * GET /admin/cover-letters
     */
    public function index(): InertiaResponse
    {
        $coverLetters = CoverLetter::query()
            ->with('resumeVersion')
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get();

        $coverLetters->each(function ($cl) {
            $cl->date_formatted = $cl->date ? $cl->date->format('M j, Y') : null;
            $cl->resume_version_label = $cl->resumeVersion?->version ?? 'N/A';
        });

        return Inertia::render('cover-letters/Index', [
            'coverLetters' => $coverLetters,
        ]);
    }

    /**
     * GET /admin/cover-letters/new
     *
     * `?application={id}` opens the form for that application's job: the
     * letter is prefilled from it and linked to it when saved. An unknown or
     * deleted application is ignored.
     */
    public function create(Request $request): InertiaResponse
    {
        $resumeVersions = ResumeVersion::query()
            ->orderByDesc('is_current')
            ->orderByDesc('id')
            ->get();

        $application = $request->filled('application')
            ? Application::query()->find($request->integer('application'), ['id', 'company_name', 'position'])
            : null;

        return Inertia::render('cover-letters/Create', [
            'resumeVersions' => $resumeVersions,
            'application' => $application?->only(['id', 'company_name', 'position']),
        ]);
    }

    /**
     * POST /admin/cover-letters
     */
    public function store(StoreCoverLetterRequest $request): RedirectResponse
    {
        $coverLetter = CoverLetter::create($request->validated());

        $this->generateDocuments($coverLetter);

        return redirect()
            ->route('admin.cover-letters.edit', $coverLetter)
            ->with('success', 'Cover letter created.');
    }

    /**
     * GET /admin/cover-letters/{coverLetter}
     */
    public function edit(CoverLetter $coverLetter): InertiaResponse
    {
        $resumeVersions = ResumeVersion::query()
            ->orderByDesc('is_current')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('cover-letters/Edit', [
            'coverLetter' => $this->serializeCoverLetter($coverLetter),
            'resumeVersions' => $resumeVersions,
        ]);
    }

    /**
     * GET /admin/cover-letters/{coverLetter}/preview
     */
    public function preview(CoverLetter $coverLetter): InertiaResponse
    {
        $coverLetter->load('resumeVersion.personalInfo');

        $converter = new CommonMarkConverter;
        $messageBodyHtml = $coverLetter->message_body
            ? $converter->convert($coverLetter->message_body)->getContent()
            : '';
        $personalInformation = $coverLetter->resumeVersion?->personalInfo;

        return Inertia::render('cover-letters/Preview', [
            'personal' => $personalInformation,
            'coverLetter' => $this->serializeCoverLetter($coverLetter),
            'messageBodyHtml' => $messageBodyHtml,
            'docxExists' => $coverLetter->docxExists(),
            'pdfExists' => $coverLetter->pdfExists(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serializeCoverLetter(CoverLetter $coverLetter): array
    {
        $attributes = $coverLetter->toArray();
        $attributes['date'] = $coverLetter->date?->toDateString();

        return $attributes;
    }

    /**
     * PUT /admin/cover-letters/{coverLetter}
     */
    public function update(StoreCoverLetterRequest $request, CoverLetter $coverLetter): RedirectResponse
    {
        $coverLetter->update($request->validated());

        $this->generateDocuments($coverLetter);

        return redirect()
            ->route('admin.cover-letters.edit', $coverLetter)
            ->with('success', 'Cover letter updated.');
    }

    /**
     * DELETE /admin/cover-letters/{coverLetter}
     */
    public function destroy(CoverLetter $coverLetter): RedirectResponse
    {
        if ($coverLetter->docxExists()) {
            unlink($coverLetter->docx_path);
        }

        if ($coverLetter->pdfExists()) {
            unlink($coverLetter->pdf_path);
        }

        $coverLetter->delete();

        return redirect()
            ->route('admin.cover-letters.index')
            ->with('success', 'Cover letter deleted.');
    }

    /**
     * GET /admin/cover-letters/{coverLetter}/download/docx
     */
    public function downloadDocx(Request $request, CoverLetter $coverLetter): BinaryFileResponse|RedirectResponse
    {
        $result = $this->documentService->ensureDocx($coverLetter);

        if (! $result['success']) {
            return redirect()
                ->route('admin.cover-letters.edit', $coverLetter)
                ->with('error', 'DOCX generation failed: '.($result['error'] ?? 'Unknown error'));
        }

        $this->downloadLogger->log($coverLetter, 'docx', $result['served_cached_document'], $request->ip() ?? '');

        $filename = $coverLetter->generateFilename().'.docx';
        $deleteAfterServe = config('resume.document_retention_mode') === 'delete_after_serve';

        if ($deleteAfterServe) {
            $coverLetter->forceFill(['docx_path' => null])->save();
        }

        $response = response()->download(
            $result['path'],
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        );

        if ($deleteAfterServe) {
            $response->deleteFileAfterSend(true);
        }

        return $response;
    }

    /**
     * GET /admin/cover-letters/{coverLetter}/download/pdf
     */
    public function downloadPdf(Request $request, CoverLetter $coverLetter): BinaryFileResponse|RedirectResponse
    {
        $result = $this->documentService->ensurePdf($coverLetter);

        if (! $result['success']) {
            return redirect()
                ->route('admin.cover-letters.edit', $coverLetter)
                ->with('error', 'PDF generation failed: '.($result['error'] ?? 'Unknown error'));
        }

        $this->downloadLogger->log($coverLetter, 'pdf', $result['served_cached_document'], $request->ip() ?? '');

        $filename = $coverLetter->generateFilename().'.pdf';
        $deleteAfterServe = config('resume.document_retention_mode') === 'delete_after_serve';

        if ($deleteAfterServe) {
            $coverLetter->forceFill(['pdf_path' => null])->save();
        }

        $response = response()->download(
            $result['path'],
            $filename,
            ['Content-Type' => 'application/pdf']
        );

        if ($deleteAfterServe) {
            $response->deleteFileAfterSend(true);
        }

        return $response;
    }

    /**
     * Invalidate any previously rendered documents for the cover letter so
     * the next download renders fresh from the saved content.
     */
    protected function generateDocuments(CoverLetter $coverLetter): void
    {
        $coverLetter->invalidateDocuments();
    }
}
