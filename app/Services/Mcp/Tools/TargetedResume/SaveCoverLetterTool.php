<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Contracts\Mcp\AiToolHandlerContract;
use App\Models\AiConversation;
use App\Services\TargetedResumeService;

class SaveCoverLetterTool implements AiToolHandlerContract
{
    public function __construct(
        private AiConversation $conversation,
        private TargetedResumeService $targetedResumeService,
    ) {}

    public function name(): string
    {
        return 'save_cover_letter';
    }

    public function description(): string
    {
        return 'Save the finalized cover letter and generate DOCX and PDF. Call this when the user approves the cover letter.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cover_letter_content' => ['type' => 'string'],
            ],
            'required' => ['cover_letter_content'],
        ];
    }

    public function handle(array $input): array
    {
        $content = (string) ($input['cover_letter_content'] ?? '');

        if ($content === '') {
            return ['error' => 'cover_letter_content must not be empty.'];
        }

        $coverLetter = $this->targetedResumeService->saveCoverLetter($this->conversation, $content);

        return [
            'success' => true,
            'cover_letter_id' => $coverLetter->id,
        ];
    }
}
