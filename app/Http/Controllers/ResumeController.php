<?php

namespace App\Http\Controllers;

use App\Contracts\ResumeDataServiceContract;
use App\Contracts\ResumeVersionServiceContract;
use App\Models\ResumeDownload;
use App\Models\ResumeShareCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ResumeController extends Controller
{
    public function __construct(
        protected ResumeDataServiceContract $resumeData,
        protected ResumeVersionServiceContract $versionService,
    ) {
    }

    /**
     * GET /resume/enter-code
     * Display page for manually entering a share code
     */
    public function enterCode(Request $request): View|RedirectResponse
    {
        // If already authenticated, redirect to resume page
        if ($request->user()) {
            return redirect()->route('resume.index');
        }

        // If already have valid code in session, redirect to resume
        if (session('resume_share_code')) {
            $shareCode = ResumeShareCode::valid()->find(session('resume_share_code'));
            if ($shareCode) {
                return redirect()->route('resume.index');
            }
            // Invalid/expired code in session - clear it
            session()->forget('resume_share_code');
        }

        return view('resume.enter-code');
    }

    /**
     * GET /resume
     * Display resume based on Accept header (HTML, JSON, or plain text)
     */
    public function index(Request $request): Response|View|JsonResponse
    {
        $data = $this->resumeData->getDisplayData();

        // Share code users get full access (read + save), authenticated users need permission
        $canSave = session('resume_share_code')
            ? true
            : ($request->user()?->can('save-resume') ?? false);

        // Content negotiation based on Accept header
        $acceptHeader = $request->header('Accept', 'text/html');

        if (str_contains($acceptHeader, 'application/json')) {
            return response()->json($data);
        }

        if (str_contains($acceptHeader, 'text/plain')) {
            return response()
                ->view('resume.plain-text', ['data' => $data])
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        // Default: HTML
        return view('resume.index', [
            'data' => $data,
            'canSave' => $canSave,
        ]);
    }

    /**
     * Validate user has permission to download resume
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function validateDownloadPermission(Request $request): void
    {
        if (!session('resume_share_code') && !$request->user()?->can('save-resume')) {
            if ($request->wantsJson()) {
                response()->json([
                    'code' => 403,
                    'status' => 'failed',
                    'message' => 'You do not have permission to download the resume.',
                ], 403)->send();
                exit;
            }
            abort(403, 'You do not have permission to download the resume.');
        }
    }

    /**
     * Handle file not found scenario
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    private function handleFileNotFound(Request $request): never
    {
        if ($request->wantsJson()) {
            response()->json([
                'code' => 404,
                'status' => 'failed',
                'message' => 'Resume not available for download.',
            ], 404)->send();
            exit;
        }
        abort(404, 'Resume not available for download.');
    }

    /**
     * Track resume download in database
     */
    private function trackDownload(Request $request): void
    {
        $version = $this->versionService->getCurrentVersion();
        ResumeDownload::record(
            version: $version,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            shareCodeId: session('resume_share_code'),
            userId: $request->user()?->id
        );
    }

    /**
     * Return binary file download response
     */
    private function downloadFile(string $path, string $mimeType): BinaryFileResponse
    {
        $filename = basename($path);
        return response()->download($path, $filename, [
            'Content-Type' => $mimeType,
        ]);
    }

    /**
     * GET /resume/download
     * Display download page with options for DOCX and PDF
     */
    public function download(Request $request): Response|View|JsonResponse
    {
        $this->validateDownloadPermission($request);

        return view('resume.download.index', [
            'docx_exists' => !!$this->versionService->getLatestDocxPath(),
            'pdf_exists' => !!$this->versionService->getLatestPdfPath(),
        ]);
    }

    /**
     * GET /resume/download/docx
     * Download the pre-generated DOCX file
     */
    public function downloadDocx(Request $request): BinaryFileResponse|JsonResponse
    {
        $this->validateDownloadPermission($request);

        $docxPath = $this->versionService->getLatestDocxPath();
        if (!$docxPath) {
            $this->handleFileNotFound($request);
        }

        $this->trackDownload($request);

        return $this->downloadFile(
            $docxPath,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
    }

    /**
     * GET /resume/download/pdf
     * Download the pre-generated PDF file
     */
    public function downloadPdf(Request $request): BinaryFileResponse|JsonResponse
    {
        $this->validateDownloadPermission($request);

        $pdfPath = $this->versionService->getLatestPdfPath();
        if (!$pdfPath) {
            $this->handleFileNotFound($request);
        }

        $this->trackDownload($request);

        return $this->downloadFile($pdfPath, 'application/pdf');
    }
}
