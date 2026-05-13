<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Contracts\Mcp\AiToolHandlerContract;
use App\Models\AiConversation;
use App\Services\TargetedResumeService;

class SaveTailoredResumeTool implements AiToolHandlerContract
{
    public function __construct(
        private AiConversation $conversation,
        private TargetedResumeService $targetedResumeService,
    ) {}

    public function name(): string
    {
        return 'save_tailored_resume';
    }

    public function description(): string
    {
        return 'Save the finalized tailored resume, generate DOCX and PDF, and mark the conversation completed. Call this when the user approves the resume.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tailored_content' => ['type' => 'string'],
                'fit_score' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
            'required' => ['tailored_content'],
        ];
    }

    public function handle(array $input): array
    {
        $tailoredContent = (string) ($input['tailored_content'] ?? '');

        if ($tailoredContent === '') {
            return ['error' => 'tailored_content must not be empty.'];
        }

        $fitScore = isset($input['fit_score']) ? (int) $input['fit_score'] : null;

        $targetedResume = $this->targetedResumeService->saveTailoredResume(
            $this->conversation,
            $tailoredContent,
            $fitScore,
        );

        return [
            'success' => true,
            'targeted_resume_id' => $targetedResume->id,
            'status' => $targetedResume->status->value,
        ];
    }
}
