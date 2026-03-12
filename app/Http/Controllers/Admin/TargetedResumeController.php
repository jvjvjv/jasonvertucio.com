<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartTargetedResumeRequest;
use App\Http\Requests\UpdateTargetedResumeConversationRequest;
use App\Models\AiConversation;
use App\Models\AiSystem;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Services\TargetedResumeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use League\CommonMark\CommonMarkConverter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TargetedResumeController extends Controller
{
    public function __construct(
        private TargetedResumeService $targetedResumeService,
    ) {
    }

    /**
     * List all targeted resumes.
     */
    public function index(): View
    {
        $conversations = AiConversation::with(['aiSystem', 'targetedResume.resumeVersion'])
            ->withCount(['messages' => fn($query) => $query->where('role', '!=', 'system')])
            ->where('feature', 'targeted-resume')
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.resume.targeted.index', compact('conversations'));
    }

    /**
     * Show the form to start a new targeted resume session.
     */
    public function create(): View
    {
        $systems = AiSystem::active()->orderBy('name')->get();
        $defaultSystemId = AiSystem::defaultForFeature('targeted-resume')?->id;

        return view('admin.resume.targeted.create', compact('systems', 'defaultSystemId'));
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
    public function show(AiConversation $conversation): View
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
        $tailoredPreviewHtml = null;
        $shouldAutoStart = ($conversation->messages->where('role', 'assistant')->count() === 0)
            && ((bool) data_get($conversation->context, 'auto_start_pending', false));

        if ($targetedResume) {
            $tailoredData = $targetedResume->tailored_data ?? [];
            $tailoredFormat = data_get($tailoredData, 'format');
            $tailoredContent = data_get($tailoredData, 'content');

            if (!is_string($tailoredContent) || $tailoredContent === '') {
                $tailoredContent = data_get($tailoredData, 'markdown') ?: data_get($tailoredData, 'html');
                $tailoredFormat = data_get($tailoredData, 'markdown') ? 'markdown' : (data_get($tailoredData, 'html') ? 'html' : $tailoredFormat);
            }

            if (is_string($tailoredContent) && $tailoredContent !== '') {
                if ($tailoredFormat === 'markdown') {
                    $tailoredPreviewHtml = (new CommonMarkConverter())->convert($tailoredContent)->getContent();
                } else {
                    $tailoredPreviewHtml = $tailoredContent;
                }
            }
        }

        return view('admin.resume.targeted.show', compact('conversation', 'messages', 'targetedResume', 'tailoredPreviewHtml', 'shouldAutoStart'));
    }

    /**
     * Stream a chat response via SSE.
     */
    public function chat(Request $request, AiConversation $conversation): StreamedResponse
    {
        $request->validate([
            'message' => ['nullable', 'string'],
        ]);

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
            'tailored_content' => ['required_without:tailored_html', 'string'],
            'tailored_html' => ['nullable', 'string'],
            'fit_score' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tailoredContent = $request->input('tailored_content') ?? $request->input('tailored_html');

        try {
            $targetedResume = $this->targetedResumeService->saveTailoredResume(
                $conversation,
                $tailoredContent,
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

    public function updateMetadata(\App\Http\Requests\UpdateTargetedResumeConversationRequest $request, AiConversation $conversation): \Illuminate\Http\RedirectResponse {
        $this->targetedResumeService->updateConversationMetadata($conversation, $request->validated());

        return redirect()
            ->route('admin.resume.targeted.show', $conversation)
            ->with('success', 'Targeted resume chat details updated.');
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
}
