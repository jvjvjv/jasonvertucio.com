<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolHandlerContract;
use Jvjvjv\CodeTalker\Models\AiConversation;

class UpdateFitAssessmentTool implements AiToolHandlerContract
{
    public function __construct(private AiConversation $conversation) {}

    public function name(): string
    {
        return 'update_fit_assessment';
    }

    public function description(): string
    {
        return 'Persist the fit score, fit summary, company name, and job title to the conversation. Call this after Step 4.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'fit_score' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                'fit_summary' => ['type' => 'string'],
                'company_name' => ['type' => 'string'],
                'job_title' => ['type' => 'string'],
            ],
            'required' => ['fit_score', 'fit_summary'],
        ];
    }

    public function handle(array $input): array
    {
        $context = $this->conversation->context ?? [];
        $updates = [];

        $fitScore = isset($input['fit_score']) ? (int) $input['fit_score'] : null;

        if ($fitScore !== null && $fitScore >= 1 && $fitScore <= 100) {
            $updates['fit_score'] = $fitScore;
        }

        if (isset($input['fit_summary']) && $input['fit_summary'] !== '') {
            $updates['fit_summary'] = (string) $input['fit_summary'];
        }

        if (isset($input['company_name']) && $input['company_name'] !== '') {
            $updates['company_name'] = (string) $input['company_name'];
        }

        if (isset($input['job_title']) && $input['job_title'] !== '') {
            $updates['job_title'] = (string) $input['job_title'];
        }

        if ($updates !== []) {
            $this->conversation->update(['context' => array_merge($context, $updates)]);
            $this->conversation->refresh();
        }

        return ['success' => true, 'updated' => array_keys($updates)];
    }
}
