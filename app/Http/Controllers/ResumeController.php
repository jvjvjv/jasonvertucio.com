<?php

namespace App\Http\Controllers;

use App\Services\ResumeDataService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;

class ResumeController extends Controller
{
    protected ResumeDataService $resumeData;

    public function __construct(ResumeDataService $resumeData)
    {
        $this->resumeData = $resumeData;
    }

    /**
     * GET /resume
     * Display resume based on Accept header (HTML, JSON, or plain text)
     */
    public function index(Request $request): Response|View|JsonResponse
    {
        $data = $this->resumeData->getDisplayData();
        $canSave = $request->user()->can('save-resume');

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
     * POST /resume/docx
     * Create hyperbole cookie and redirect to GET /resume/docx
     */
    public function initiateDownload(Request $request): RedirectResponse
    {
        $timestamp = time();
        $cookie = Cookie::make('hyperbole', $timestamp, config('resume.download_expiration'));

        return redirect()
            ->route('resume.docx.download')
            ->withCookie($cookie);
    }

    /**
     * GET /resume/docx
     * Validate cookie and serve DOCX generation page
     */
    public function downloadDocx(Request $request): Response|View|JsonResponse
    {
        $cookieValue = $request->cookie('hyperbole');
        $serverTime = time();

        // Validate cookie exists and is within 10 minutes
        if (!$cookieValue || abs($serverTime - (int)$cookieValue) > 600) {
            if ($request->wantsJson()) {
                return response()->json([
                    'code' => 403,
                    'status' => 'failed',
                    'message' => 'Direct download forbidden.',
                ], 403);
            }

            abort(403, 'Direct download forbidden.');
        }

        // Load template and data
        $templatePath = config('resume.template');
        $templateContent = file_get_contents($templatePath);
        $templateBase64 = base64_encode($templateContent);

        $data = $this->resumeData->getDocxData();

        return view('resume.docx-download', [
            'templateBase64' => $templateBase64,
            'resumeData' => $data,
        ]);
    }

    /**
     * POST /resume/docx/completed
     * Accept uploaded file and store it
     */
    public function storeGeneratedDocx(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:docx|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $path = config('resume.saved_documents');

        // Ensure directory exists
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        $filename = $file->getClientOriginalName();
        $file->move($path, $filename);

        return response()->json([
            'status' => 'success',
            'message' => 'Resume saved successfully.',
            'filename' => $filename,
        ]);
    }
}
