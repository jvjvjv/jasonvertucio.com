<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Services\TargetedResumeService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Log;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('save-tailored-resume')]
#[Description('Save the finalized tailored resume, generate DOCX and PDF, and mark the conversation completed. Call this when the user approves the resume.')]
class SaveTailoredResumeTool extends AuthorizedResumeTool
{
    public function __construct(
        ToolContext $context,
        private TargetedResumeService $targetedResumeService,
    ) {
        parent::__construct($context);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'tailored_content' => $schema->string()->required(),
            'fit_score' => $schema->integer()->min(1)->max(100),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        Log::info('targeted-resume.save-tailored-resume: request received', [
            'conversation_id' => $this->context->conversation?->id,
            'user_id' => $this->context->userId,
        ]);

        if ($response = $this->guard()) {
            Log::warning('targeted-resume.save-tailored-resume: access denied', [
                'conversation_id' => $this->context->conversation?->id,
                'user_id' => $this->context->userId,
            ]);

            return $response;
        }

        $tailoredContent = (string) ($request->get('tailored_content') ?? '');

        if ($tailoredContent === '') {
            Log::warning('targeted-resume.save-tailored-resume: empty tailored_content payload', [
                'conversation_id' => $this->context->conversation?->id,
                'user_id' => $this->context->userId,
            ]);

            return Response::error('tailored_content must not be empty.');
        }

        $fitScore = $request->get('fit_score') !== null ? (int) $request->get('fit_score') : null;

        Log::debug('targeted-resume.save-tailored-resume: payload validated', [
            'conversation_id' => $this->context->conversation?->id,
            'user_id' => $this->context->userId,
            'tailored_content_length' => strlen($tailoredContent),
            'fit_score' => $fitScore,
        ]);

        try {
            $targetedResume = $this->targetedResumeService->saveTailoredResume(
                $this->context->conversation,
                $tailoredContent,
                $fitScore,
            );

            Log::info('targeted-resume.save-tailored-resume: save completed', [
                'conversation_id' => $this->context->conversation?->id,
                'targeted_resume_id' => $targetedResume->id,
                'status' => $targetedResume->status->value,
            ]);

            return Response::structured([
                'success' => true,
                'targeted_resume_id' => $targetedResume->id,
                'status' => $targetedResume->status->value,
                '_page_reload' => true,
            ]);
        } catch (\Throwable $throwable) {
            Log::error('targeted-resume.save-tailored-resume: save failed', [
                'conversation_id' => $this->context->conversation?->id,
                'user_id' => $this->context->userId,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return Response::error('Failed to save the tailored resume. Check the application log for details.');
        }
    }
}
