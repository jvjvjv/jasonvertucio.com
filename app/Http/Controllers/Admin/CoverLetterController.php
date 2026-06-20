<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCoverLetterRequest;
use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Services\CoverLetterDocumentService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CoverLetterController extends Controller
{
    public function __construct(
        protected CoverLetterDocumentService $documentService,
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
     */
    public function create(): InertiaResponse
    {
        $resumeVersions = ResumeVersion::query()
            ->orderByDesc('is_current')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('cover-letters/Create', [
            'resumeVersions' => $resumeVersions,
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
            ->with('success', 'Cover letter created and documents generated.');
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
            ->with('success', 'Cover letter updated and documents regenerated.');
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
    public function downloadDocx(CoverLetter $coverLetter): BinaryFileResponse|RedirectResponse
    {
        if (! $coverLetter->docxExists()) {
            return redirect()
                ->route('admin.cover-letters.edit', $coverLetter)
                ->with('error', 'DOCX file not found. Save the cover letter to regenerate it.');
        }

        $filename = $coverLetter->generateFilename().'.docx';

        return response()->download(
            $coverLetter->docx_path,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
        );
    }

    /**
     * GET /admin/cover-letters/{coverLetter}/download/pdf
     */
    public function downloadPdf(CoverLetter $coverLetter): BinaryFileResponse|RedirectResponse
    {
        if (! $coverLetter->pdfExists()) {
            return redirect()
                ->route('admin.cover-letters.edit', $coverLetter)
                ->with('error', 'PDF file not found. Save the cover letter to regenerate it.');
        }

        $filename = $coverLetter->generateFilename().'.pdf';

        return response()->download(
            $coverLetter->pdf_path,
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * Generate DOCX and PDF documents for the cover letter.
     */
    protected function generateDocuments(CoverLetter $coverLetter): void
    {
        $docxResult = $this->documentService->generateDocx($coverLetter);

        if ($docxResult['success']) {
            $this->documentService->generatePdf($coverLetter);
        }
    }
}
