<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Models\TargetedResume;
use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolHandlerContract;
use Jvjvjv\CodeTalker\Models\AiConversation;

class GetTargetedResumeContextTool implements AiToolHandlerContract
{
    public function __construct(private AiConversation $conversation) {}

    public function name(): string
    {
        return 'get_targeted_resume_context';
    }

    public function description(): string
    {
        return 'Load the full context for a finalized targeted resume: the original job description, '
            . 'the tailored resume markdown, and the cover letter (if one exists). '
            . 'Use this to reload context for interview prep, cover letter revisions, or fit analysis.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'targeted_resume_id' => [
                    'type' => 'integer',
                    'description' => 'The ID of the targeted resume to load context for.',
                ],
            ],
            'required' => ['targeted_resume_id'],
        ];
    }

    public function handle(array $input): array
    {
        $resume = TargetedResume::whereHas(
            'conversation',
            fn ($q) => $q->where('user_id', $this->conversation->user_id)
        )->find((int) $input['targeted_resume_id']);

        if ($resume === null) {
            return ['error' => 'Targeted resume not found.'];
        }

        $coverLetter = $resume->coverLetters()->latest()->first();

        return [
            'job_description' => [
                'company' => $resume->company_name,
                'position' => $resume->position,
                'text' => $resume->job_description,
            ],
            'tailored_resume' => $resume->tailored_data['markdown'] ?? null,
            'cover_letter' => $coverLetter !== null ? [
                'greeting' => $coverLetter->greeting,
                'body' => $coverLetter->message_body,
                'closing' => $coverLetter->closing,
                'signature' => $coverLetter->signature,
            ] : null,
            'meta' => [
                'fit_score' => $resume->fit_score,
                'fit_summary' => $resume->fit_summary,
                'status' => $resume->status->value,
                'title' => $resume->title,
            ],
        ];
    }
}
