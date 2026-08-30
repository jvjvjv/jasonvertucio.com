<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ResumeDataServiceContract;
use App\Contracts\ResumeVersionServiceContract;
use App\Http\Controllers\Controller;
use App\Mail\ResumeUpdated;
use App\Models\ResumeEditCandidate;
use App\Models\ResumeShareCode;
use App\Models\ResumeVersion;
use App\Services\ResumeEditCandidateService;
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
        protected ResumeEditCandidateService $candidateService,
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
     * Show the resume editor with all JSON data. Reviewing/approving/rejecting
     * a specific AI-drafted candidate revision happens on the public resume
     * preview page (`/resume?revision=`) instead of here.
     */
    public function edit(): InertiaResponse
    {
        $liveVersion = ResumeVersion::current()->first();

        $data = $this->dataService->getAllEditableData();
        $version = $this->versionService->getCurrentVersion();
        $docxExists = $this->versionService->docxExistsForCurrentVersion();
        $availableVersions = $this->versionService->getAvailableVersions();

        // Get count of recipients who will be notified on update
        $notificationRecipientCount = ResumeShareCode::shouldNotifyOnUpdate()->count();

        $pendingCandidates = $liveVersion
            ? ResumeEditCandidate::query()
                ->where('base_resume_version_id', $liveVersion->id)
                ->pending()
                ->orderBy('revision_number')
                ->get(['id', 'revision_number', 'last_edited_at'])
            : collect();

        return Inertia::render('resume/Editor', [
            'data' => $data,
            'version' => $version,
            'docxExists' => $docxExists,
            'availableVersions' => $availableVersions,
            'mailConfigured' => $this->isMailConfigured(),
            'notificationRecipientCount' => $notificationRecipientCount,
            'pendingCandidates' => $pendingCandidates,
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

        $liveVersion = ResumeVersion::current()->first();

        if ($liveVersion !== null && $this->candidateService->hasPendingCandidateFor($liveVersion)) {
            $message = 'An AI-drafted resume revision is pending review. Approve or reject it before making manual edits.';

            if ($request->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                ], 409);
            }

            return redirect()
                ->route('admin.resume.editor')
                ->with('error', $message);
        }

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

    /**
     * Where to send the admin back to after resolving a candidate: the admin
     * editor (default) or the public resume preview page, chosen by the
     * `redirect_to` field the reviewing page submits.
     */
    private function candidateResolvedRedirectTarget(Request $request): string
    {
        return $request->input('redirect_to') === 'preview'
            ? route('resume.index')
            : route('admin.resume.editor');
    }

    /**
     * POST /admin/resume/candidates/{candidate}/approve
     * Materialize a pending AI-drafted candidate as the new live resume version
     * at the version submitted by the reviewer.
     */
    public function approveCandidate(Request $request, ResumeEditCandidate $candidate): RedirectResponse
    {
        $target = $this->candidateResolvedRedirectTarget($request);

        if ($candidate->status !== 'pending') {
            return redirect($target)->with('error', 'This candidate has already been resolved.');
        }

        $validated = $request->validate([
            'version' => ['required', 'string', 'regex:/^\d{4}\.\d+\.\d+$/'],
        ]);

        try {
            $result = $this->candidateService->approve($candidate, $request->user()->id, $validated['version']);
        } catch (\InvalidArgumentException $exception) {
            return redirect($target)->with('error', $exception->getMessage());
        }

        $message = isset($result['error'])
            ? 'Candidate approved, but document generation failed: '.$result['error']
            : 'Candidate approved and is now the live resume.';

        return redirect($target)->with(isset($result['error']) ? 'error' : 'success', $message);
    }

    /**
     * POST /admin/resume/candidates/{candidate}/reject
     * Permanently delete a pending AI-drafted candidate. No undo.
     */
    public function rejectCandidate(Request $request, ResumeEditCandidate $candidate): RedirectResponse
    {
        $target = $this->candidateResolvedRedirectTarget($request);

        if ($candidate->status !== 'pending') {
            return redirect($target)->with('error', 'This candidate has already been resolved.');
        }

        $this->candidateService->reject($candidate);

        return redirect($target)->with('success', 'Candidate rejected and permanently deleted.');
    }
}
