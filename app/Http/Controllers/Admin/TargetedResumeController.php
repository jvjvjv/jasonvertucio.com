<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartTargetedResumeRequest;
use App\Models\AiConversation;
use App\Models\AiSystem;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Services\TargetedResumeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
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
        $targetedResumes = TargetedResume::with(['resumeVersion', 'conversation'])
            ->orderByDesc('created_at')
            ->get();

        return view('admin.resume.targeted.index', compact('targetedResumes'));
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

        return view('admin.resume.targeted.show', compact('conversation', 'messages', 'targetedResume'));
    }

    /**
     * Stream a chat response via SSE.
     */
    public function chat(Request $request, AiConversation $conversation): StreamedResponse
    {
        $request->validate([
            'message' => ['required', 'string'],
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
            'tailored_data' => ['required', 'array'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'fit_score' => ['nullable', 'integer', 'min:1', 'max:100'],
            'fit_summary' => ['nullable', 'string'],
        ]);

        // Merge any overrides into the conversation context
        $context = $conversation->context ?? [];
        if ($request->filled('company_name')) {
            $context['company_name'] = $request->input('company_name');
        }
        if ($request->filled('position')) {
            $context['job_title'] = $request->input('position');
        }
        if ($request->filled('fit_score')) {
            $context['fit_score'] = $request->input('fit_score');
        }
        if ($request->filled('fit_summary')) {
            $context['fit_summary'] = $request->input('fit_summary');
        }
        $conversation->update(['context' => $context]);

        $targetedResume = $this->targetedResumeService->saveTailoredResume(
            $conversation,
            $request->input('tailored_data'),
        );

        return response()->json([
            'success' => true,
            'targeted_resume_id' => $targetedResume->id,
            'message' => 'Targeted resume saved successfully.',
        ]);
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
