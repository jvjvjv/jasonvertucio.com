<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ResumeUpdated;
use App\Models\ResumeShareCode;
use App\Services\ResumeDataService;
use App\Services\ResumeVersionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ResumeEditorController extends Controller
{
    protected ResumeDataService $dataService;
    protected ResumeVersionService $versionService;

    public function __construct(ResumeDataService $dataService, ResumeVersionService $versionService)
    {
        $this->dataService = $dataService;
        $this->versionService = $versionService;
    }

    /**
     * Check if mail is properly configured.
     */
    protected function isMailConfigured(): bool
    {
        $host = config('mail.host');
        $username = config('mail.username');

        return !empty($host) && !empty($username);
    }

    /**
     * GET /admin/resume/editor
     * Show the resume editor with all JSON data
     */
    public function edit(): View
    {
        $data = $this->dataService->getAllEditableData();
        $version = $this->versionService->getCurrentVersion();
        $docxExists = $this->versionService->docxExistsForCurrentVersion();
        $availableVersions = $this->versionService->getAvailableVersions();

        // Get count of recipients who will be notified on update
        $notificationRecipientCount = ResumeShareCode::shouldNotifyOnUpdate()->count();

        return view('admin.resume.editor', [
            'data' => $data,
            'version' => $version,
            'docxExists' => $docxExists,
            'availableVersions' => $availableVersions,
            'mailConfigured' => $this->isMailConfigured(),
            'notificationRecipientCount' => $notificationRecipientCount,
        ]);
    }

    /**
     * POST /admin/resume/editor
     * Save all JSON data from the editor
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'string', 'regex:/^\d{4}\.\d+\.\d+$/'],
            'data' => ['required', 'array'],
            'data.personal' => ['required', 'array'],
            'data.skills' => ['required', 'array'],
            'data.technicalProfile' => ['required', 'array'],
            'data.experience' => ['required', 'array'],
            'data.education' => ['required', 'array'],
            'data.projects' => ['required', 'array'],
            'notify_recipients' => ['boolean'],
        ]);

        try {
            // Save version
            $this->versionService->setVersion($validated['version']);

            // Save all data
            $this->dataService->saveAllEditableData($validated['data']);

            // Send update notifications if requested and mail is configured
            $notifyRecipients = $validated['notify_recipients'] ?? false;
            $successMessage = 'Resume data saved successfully.';

            if ($this->isMailConfigured() && $notifyRecipients) {
                $recipientCodes = ResumeShareCode::shouldNotifyOnUpdate()->get();

                if ($recipientCodes->count() > 0) {
                    foreach ($recipientCodes as $code) {
                        try {
                            Mail::to($code->email)->queue(new ResumeUpdated($code, $validated['version']));
                        } catch (\Exception $e) {
                            \Illuminate\Support\Facades\Log::error('Failed to queue resume update email', [
                                'code' => $code->id,
                                'email' => $code->email,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    $successMessage = "Resume data saved successfully. Update notifications queued for {$recipientCodes->count()} recipient(s).";
                }
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => $successMessage,
                ]);
            }

            return redirect()
                ->route('admin.resume.editor')
                ->with('success', $successMessage);

        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->route('admin.resume.editor')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * GET /admin/resume/preview
     * Show preview of the resume with current data
     */
    public function preview(): View
    {
        $data = $this->dataService->getDisplayData();
        $version = $this->versionService->getCurrentVersion();
        $docxExists = $this->versionService->docxExistsForCurrentVersion();

        // Get count of recipients who will be notified on update
        $notificationRecipientCount = ResumeShareCode::shouldNotifyOnUpdate()->count();

        return view('admin.resume.preview', [
            'data' => $data,
            'version' => $version,
            'docxExists' => $docxExists,
            'mailConfigured' => $this->isMailConfigured(),
            'notificationRecipientCount' => $notificationRecipientCount,
        ]);
    }

    /**
     * POST /admin/resume/generate
     * Generate DOCX and PDF for the current version
     */
    public function generate(Request $request): JsonResponse|RedirectResponse
    {
        $docxResult = $this->versionService->generateDocx();

        if (!$docxResult['success']) {
            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $docxResult['error'] ?? 'Failed to generate DOCX.',
                ], 500);
            }

            return redirect()
                ->route('admin.resume.preview')
                ->with('error', $docxResult['error'] ?? 'Failed to generate DOCX.');
        }

        // Generate PDF from DOCX
        $pdfResult = $this->versionService->generatePdf();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => $pdfResult['success']
                    ? 'DOCX and PDF generated successfully.'
                    : 'DOCX generated successfully, but PDF generation failed.',
                'docx' => [
                    'path' => $docxResult['path'],
                    'size' => $docxResult['size'] ?? null,
                ],
                'pdf' => $pdfResult['success'] ? [
                    'path' => $pdfResult['path'],
                    'size' => $pdfResult['size'] ?? null,
                ] : [
                    'error' => $pdfResult['error'] ?? 'Unknown error',
                ],
            ]);
        }

        if ($pdfResult['success']) {
            return redirect()
                ->route('admin.resume.preview')
                ->with('success', 'DOCX and PDF generated successfully.');
        }

        return redirect()
            ->route('admin.resume.preview')
            ->with('warning', 'DOCX generated successfully, but PDF generation failed: ' . ($pdfResult['error'] ?? 'Unknown error'));
    }
}
