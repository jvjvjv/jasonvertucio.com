<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApplicationChatRequest;
use App\Http\Requests\BeginApplicationAnalysisRequest;
use App\Http\Requests\FinalizeCoverLetterRequest;
use App\Http\Requests\FinalizeTargetedResumeRequest;
use App\Models\Application;
use App\Services\ApplicationService;
use App\Services\TargetedResumeService;
use App\Support\AnalysisSystemGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Jvjvjv\CodeTalker\Enums\AiConversationStatus;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * An application's AI session: starting it, chatting in it, and saving the
 * documents it produces.
 */
class ApplicationSessionController extends Controller
{
    private const string NO_SESSION_MESSAGE = 'This application has no AI session. Begin an analysis first.';

    public function __construct(
        private ApplicationService $applicationService,
        private TargetedResumeService $targetedResumeService,
    ) {}

    /**
     * Attach an AI session to an application that has none.
     */
    public function analysis(BeginApplicationAnalysisRequest $request, Application $application, AnalysisSystemGuard $guard): JsonResponse
    {
        $refusal = $guard->refusal();

        if ($refusal !== null) {
            return response()->json(['error' => $refusal, 'message' => $refusal], 422);
        }

        $system = $request->validated('ai_system_id') !== null
            ? AiSystem::findOrFail($request->validated('ai_system_id'))
            : AiSystem::defaultForFeature('targeted-resume');

        if ($system === null) {
            return response()->json(['message' => 'No AI system is configured for targeted resumes.'], 422);
        }

        $this->applicationService->beginAnalysis($application, $system);

        return response()->json([
            'success' => true,
            'conversation_id' => $application->ai_conversation_id,
            'redirect' => route('admin.resume.applications.show', $application),
        ]);
    }

    /**
     * Stream a chat response via SSE.
     */
    public function chat(ApplicationChatRequest $request, Application $application): StreamedResponse|JsonResponse
    {
        $conversation = $application->conversation;

        if ($conversation === null) {
            return $this->noSessionResponse();
        }

        $message = $request->validated('message');

        Log::info('targeted-resume.chat: stream requested', [
            'application_id' => $application->id,
            'conversation_id' => $conversation->id,
            'user_id' => $request->user()?->id,
            'message_present' => $request->filled('message'),
            'message_length' => strlen((string) $message),
        ]);

        if ($conversation->status === AiConversationStatus::Pass) {
            $conversation->update(['status' => AiConversationStatus::Active]);
        }

        return response()->stream(function () use ($message, $conversation): void {
            $streamStart = microtime(true);
            set_time_limit(0);

            echo 'data: '.json_encode([
                'type' => 'status',
                'message' => 'Preparing analysis...',
            ])."\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();

            $generator = $this->targetedResumeService->continueConversation($conversation, $message);

            try {
                foreach ($generator as $chunk) {
                    echo $chunk;
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }

                Log::info('targeted-resume.chat: stream completed', [
                    'conversation_id' => $conversation->id,
                    'duration_ms' => (int) ((microtime(true) - $streamStart) * 1000),
                ]);
            } catch (\Throwable $e) {
                Log::error('targeted-resume.chat: stream failed', [
                    'conversation_id' => $conversation->id,
                    'duration_ms' => (int) ((microtime(true) - $streamStart) * 1000),
                    'error' => $e->getMessage(),
                ]);
                echo 'data: '.json_encode(['type' => 'error', 'message' => 'Stream failed unexpectedly.'])."\n\n";
                echo "data: [DONE]\n\n";
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
     * Save the targeted resume written in the session and attach it to the
     * application.
     */
    public function finalize(FinalizeTargetedResumeRequest $request, Application $application): JsonResponse
    {
        if ($application->conversation === null) {
            return $this->noSessionResponse();
        }

        try {
            $targetedResume = $this->targetedResumeService->saveTailoredResume(
                $application,
                $request->validated('tailored_content'),
                $request->validated('fit_score'),
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
     * Save the cover letter written in the session.
     */
    public function finalizeCoverLetter(FinalizeCoverLetterRequest $request, Application $application): JsonResponse
    {
        if ($application->conversation === null) {
            return $this->noSessionResponse();
        }

        try {
            $coverLetter = $this->targetedResumeService->saveCoverLetter(
                $application,
                $request->validated('cover_letter_content'),
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

    private function noSessionResponse(): JsonResponse
    {
        return response()->json(['message' => self::NO_SESSION_MESSAGE], 409);
    }
}
