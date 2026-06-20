<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ResumeDataServiceContract;
use App\Contracts\ResumeVersionServiceContract;
use App\Http\Controllers\Controller;
use App\Mail\ResumeUpdated;
use App\Models\ResumeShareCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ResumeEditorController extends Controller
{
    public function __construct(
        protected ResumeDataServiceContract $dataService,
        protected ResumeVersionServiceContract $versionService,
    ) {}

    /**
     * Check if mail is properly configured.
     */
    protected function isMailConfigured(): bool
    {
        $host = config('mail.host');
        $username = config('mail.username');

        return ! empty($host) && ! empty($username);
    }

    /**
     * GET /admin/resume/editor
     * Show the resume editor with all JSON data
     */
    public function edit(): InertiaResponse
    {
        $data = $this->dataService->getAllEditableData();
        $version = $this->versionService->getCurrentVersion();
        $docxExists = $this->versionService->docxExistsForCurrentVersion();
        $availableVersions = $this->versionService->getAvailableVersions();

        // Get count of recipients who will be notified on update
        $notificationRecipientCount = ResumeShareCode::shouldNotifyOnUpdate()->count();

        return Inertia::render('resume/Editor', [
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

            // Always regenerate documents on save
            $documentsRegenerated = false;
            $regenerationWarning = null;

            $docxResult = $this->versionService->generateDocx();

            if ($docxResult['success']) {
                $pdfResult = $this->versionService->generatePdf();
                $documentsRegenerated = true;

                if (! $pdfResult['success']) {
                    $regenerationWarning = 'PDF generation failed: '.($pdfResult['error'] ?? 'Unknown error');
                }
            } else {
                $regenerationWarning = 'DOCX generation failed: '.($docxResult['error'] ?? 'Unknown error');
            }

            // Send update notifications if requested and mail is configured
            $notifyRecipients = $validated['notify_recipients'] ?? false;
            $successMessage = 'Resume data saved successfully.';

            if ($documentsRegenerated) {
                $successMessage .= ' Documents automatically regenerated.';
                if ($regenerationWarning) {
                    $successMessage .= ' Warning: '.$regenerationWarning;
                }
            }

            if ($this->isMailConfigured() && $notifyRecipients) {
                $recipientCodes = ResumeShareCode::shouldNotifyOnUpdate()->get();

                if ($recipientCodes->count() > 0) {
                    foreach ($recipientCodes as $code) {
                        try {
                            Mail::to($code->email)->queue(new ResumeUpdated($code, $validated['version']));
                        } catch (\Exception $e) {
                            Log::error('Failed to queue resume update email', [
                                'code' => $code->id,
                                'email' => $code->email,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    $successMessage .= " Update notifications queued for {$recipientCodes->count()} recipient(s).";
                }
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => $successMessage,
                    'documents_regenerated' => $documentsRegenerated,
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
}
