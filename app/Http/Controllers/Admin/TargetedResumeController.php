<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AiConversationStatus;
use App\Enums\TargetedResumeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StartTargetedResumeRequest;
use App\Http\Requests\UpdateTargetedResumeConversationRequest;
use App\Models\AiConversation;
use App\Models\AiSystem;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Services\TargetedResumeDocumentService;
use App\Services\TargetedResumeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TargetedResumeController extends Controller
{
    public function __construct(
        private TargetedResumeService $targetedResumeService,
    ) {
    }

    /**
     * List all targeted resumes with optional filters.
     */
    public function index(Request $request): InertiaResponse
    {
        $defaultStatuses = [AiConversationStatus::Active->value, TargetedResumeStatus::Finalized->value];
        $statuses = $request->input('status', $request->has('search') ? [] : $defaultStatuses);
        $search = $request->input('search', '');

        $query = AiConversation::with(['aiSystem', 'targetedResume.resumeVersion'])
            ->withCount(['messages' => fn($q) => $q->where('role', '!=', 'system')])
            ->where('feature', 'targeted-resume');

        if (! empty($statuses)) {
            $conversationStatuses = array_intersect($statuses, array_column(AiConversationStatus::cases(), 'value'));
            $resumeStatuses = array_intersect($statuses, array_column(TargetedResumeStatus::cases(), 'value'));

            $query->where(function ($q) use ($conversationStatuses, $resumeStatuses) {
                if (! empty($conversationStatuses)) {
                    $q->whereIn('status', $conversationStatuses);
                }
                if (! empty($resumeStatuses)) {
                    $q->orWhereHas('targetedResume', fn($sub) => $sub->whereIn('status', $resumeStatuses));
                }
            });
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('targetedResume', function ($sub) use ($search) {
                    $sub->where('company_name', 'LIKE', '%' . $search . '%')
                        ->orWhere('position', 'LIKE', '%' . $search . '%');
                });

                $q->orWhere('context->company_name', 'LIKE', '%' . $search . '%')
                  ->orWhere('context->job_title', 'LIKE', '%' . $search . '%');

                $q->orWhereHas('messages', function ($sub) use ($search) {
                    $sub->where('role', '!=', 'system')
                        ->where('content', 'LIKE', '%' . $search . '%');
                });
            });
        }

        $conversations = $query->orderByDesc('updated_at')->get()->map(fn ($conv) => [
            'id' => $conv->id,
            'status' => $conv->status->value,
            'updated_at' => $conv->updated_at->diffForHumans(),
            'messages_count' => $conv->messages_count,
            'context' => $conv->context,
            'targeted_resume' => $conv->targetedResume ? [
                'id' => $conv->targetedResume->id,
                'company_name' => $conv->targetedResume->company_name,
                'position' => $conv->targetedResume->position,
                'fit_score' => $conv->targetedResume->fit_score,
                'status' => $conv->targetedResume->status->value ?? $conv->targetedResume->status,
                'resume_version' => $conv->targetedResume->resumeVersion?->version,
            ] : null,
        ]);

        $allStatuses = collect(AiConversationStatus::cases())
            ->merge(TargetedResumeStatus::cases())
            ->map(fn ($s) => ['value' => $s->value, 'label' => ucfirst($s->value)]);

        return Inertia::render('resume/targeted/Index', [
            'conversations' => $conversations,
            'allStatuses' => $allStatuses,
            'filters' => [
                'statuses' => $statuses,
                'search' => $search,
            ],
        ]);
    }

    /**
     * Show the form to start a new targeted resume session.
     */
    public function create(): InertiaResponse
    {
        $systems = AiSystem::active()->orderBy('name')->get()->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'model' => $s->model,
        ]);
        $defaultSystemId = AiSystem::defaultForFeature('targeted-resume')?->id;

        return Inertia::render('resume/targeted/Create', [
            'systems' => $systems,
            'defaultSystemId' => $defaultSystemId,
        ]);
    }

    /**
     * Start a new targeted resume conversation.
     */
    public function start(StartTargetedResumeRequest $request): JsonResponse
    {
        $system = AiSystem::findOrFail($request->validated('ai_system_id'));
        $resumeVersion = ResumeVersion::current()->firstOrFail();

        $conversation = $this->targetedResumeService->startConversation(
            system: $system,
            jobDescription: $request->validated('job_description'),
            resumeVersion: $resumeVersion,
            jobTitle: $request->validated('job_title'),
        );

        return response()->json([
            'conversation_id' => $conversation->id,
            'redirect' => route('admin.resume.targeted.show', $conversation),
        ]);
    }

    /**
     * Show a conversation with the chat interface.
     */
    public function show(AiConversation $conversation): InertiaResponse
    {
        $conversation->load('messages', 'aiSystem');

        // Get displayable messages (exclude system messages)
        $messages = $conversation->messages
            ->where('role', '!=', 'system')
            ->values()
            ->map(fn ($msg) => [
                'role' => $msg->role,
                'content' => $msg->content,
            ])
            ->toArray();

        $targetedResume = $conversation->targetedResume;
        $coverLetter = $targetedResume?->coverLetters()->latest()->first();
        $shouldAutoStart = ($conversation->messages->where('role', 'assistant')->count() === 0)
            && ((bool) data_get($conversation->context, 'auto_start_pending', false));

        return Inertia::render('resume/targeted/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'status' => $conversation->status->value,
                'title' => $conversation->title,
                'context' => $conversation->context,
                'ai_system_name' => $conversation->aiSystem?->name,
            ],
            'messages' => $messages,
            'targetedResume' => $targetedResume ? [
                'id' => $targetedResume->id,
                'company_name' => $targetedResume->company_name,
                'position' => $targetedResume->position,
                'fit_score' => $targetedResume->fit_score,
                'status' => $targetedResume->status->value ?? $targetedResume->status,
                'docx_path' => $targetedResume->docx_path ? true : false,
                'pdf_path' => $targetedResume->pdf_path ? true : false,
            ] : null,
            'coverLetter' => $coverLetter ? [
                'id' => $coverLetter->id,
            ] : null,
            'shouldAutoStart' => $shouldAutoStart,
        ]);
    }

    /**
     * Stream a chat response via SSE.
     */
    public function chat(Request $request, AiConversation $conversation): StreamedResponse
    {
        $request->validate([
            'message' => ['nullable', 'string'],
        ]);

        // Re-activate conversations that were marked as passed
        if ($conversation->status === AiConversationStatus::Pass) {
            $conversation->update(['status' => AiConversationStatus::Active]);
        }

        return response()->stream(function () use ($request, $conversation) {
            $generator = $this->targetedResumeService->continueConversation(
                $conversation,
                $request->input('message'),
            );

            foreach ($generator as $chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Finalize and save a targeted resume from the conversation.
     */
    public function finalize(Request $request, AiConversation $conversation): JsonResponse
    {
        $request->validate([
            'tailored_content' => ['required', 'string'],
            'fit_score' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        try {
            $targetedResume = $this->targetedResumeService->saveTailoredResume(
                $conversation,
                $request->input('tailored_content'),
                $request->input('fit_score'),
            );
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'targeted_resume_id' => $targetedResume->id,
            'message' => 'Targeted resume saved successfully.',
        ]);
    }

    /**
     * Finalize a cover letter from the conversation.
     */
    public function finalizeCoverLetter(Request $request, AiConversation $conversation): JsonResponse
    {
        $request->validate([
            'cover_letter_content' => ['required', 'string'],
        ]);

        try {
            $coverLetter = $this->targetedResumeService->saveCoverLetter(
                $conversation,
                $request->input('cover_letter_content'),
            );
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'cover_letter_id' => $coverLetter->id,
            'message' => 'Cover letter saved successfully.',
        ]);
    }

    public function updateMetadata(\App\Http\Requests\UpdateTargetedResumeConversationRequest $request, AiConversation $conversation): \Illuminate\Http\RedirectResponse {
        $this->targetedResumeService->updateConversationMetadata($conversation, $request->validated());

        return redirect()
            ->route('admin.resume.targeted.show', $conversation)
            ->with('success', 'Targeted resume chat details updated.');
    }

    /**
     * Regenerate DOCX and PDF for a targeted resume.
     */
    public function regenerate(TargetedResume $targetedResume, TargetedResumeDocumentService $documentService): RedirectResponse
    {
        $docxResult = $documentService->generateDocx($targetedResume);

        if (!$docxResult['success']) {
            return redirect()->route('admin.resume.targeted.index')
                ->with('error', 'DOCX generation failed: ' . ($docxResult['error'] ?? 'Unknown error'));
        }

        $pdfResult = $documentService->generatePdf($targetedResume);

        $message = 'DOCX regenerated successfully.';
        if ($pdfResult['success']) {
            $message = 'DOCX and PDF regenerated successfully.';
        }

        return redirect()->route('admin.resume.targeted.index')->with('success', $message);
    }

    /**
     * Download a targeted resume document.
     */
    public function download(TargetedResume $targetedResume, string $format): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path = match ($format) {
            'docx' => $targetedResume->docx_path,
            'pdf' => $targetedResume->pdf_path,
            default => abort(404),
        };

        if (!$path || !file_exists($path)) {
            abort(404, 'Document not found. It may not have been generated yet.');
        }

        $filename = $targetedResume->generateFilename() . '.' . $format;

        return response()->download($path, $filename);
    }

    /**
     * Mark a conversation as passed (declined the opportunity).
     */
    public function pass(AiConversation $conversation): RedirectResponse
    {
        $conversation->update(['status' => AiConversationStatus::Pass]);

        return redirect()->route('admin.resume.targeted.index')
            ->with('success', 'Conversation marked as passed.');
    }

    /**
     * Soft-delete a conversation.
     */
    public function destroy(AiConversation $conversation): RedirectResponse
    {
        $conversation->delete();

        return redirect()->route('admin.resume.targeted.index')
            ->with('success', 'Conversation deleted.');
    }
}
