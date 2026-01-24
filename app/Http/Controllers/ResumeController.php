<?php

namespace App\Http\Controllers;

use App\Models\ResumeDownload;
use App\Services\ResumeDataService;
use App\Services\ResumeVersionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ResumeController extends Controller
{
    protected ResumeDataService $resumeData;
    protected ResumeVersionService $versionService;

    public function __construct(ResumeDataService $resumeData, ResumeVersionService $versionService)
    {
        $this->resumeData = $resumeData;
        $this->versionService = $versionService;
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
     * GET /resume/download
     * Download the pre-generated DOCX file
     */
    public function download(Request $request): BinaryFileResponse|JsonResponse
    {
        // Share code users get full access, authenticated users need save-resume permission
        if (!session('resume_share_code') && !$request->user()?->can('save-resume')) {
            if ($request->wantsJson()) {
                return response()->json([
                    'code' => 403,
                    'status' => 'failed',
                    'message' => 'You do not have permission to download the resume.',
                ], 403);
            }
            abort(403, 'You do not have permission to download the resume.');
        }

        $docxPath = $this->versionService->getLatestDocxPath();

        if (!$docxPath) {
            if ($request->wantsJson()) {
                return response()->json([
                    'code' => 404,
                    'status' => 'failed',
                    'message' => 'Resume not available for download.',
                ], 404);
            }
            abort(404, 'Resume not available for download.');
        }

        // Track the download
        $version = $this->versionService->getCurrentVersion();
        ResumeDownload::record(
            version: $version,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            shareCodeId: session('resume_share_code'),
            userId: $request->user()?->id
        );

        $filename = basename($docxPath);

        return response()->download($docxPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }
}
