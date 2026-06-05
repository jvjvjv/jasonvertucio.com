<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolHandlerContract;
use Jvjvjv\CodeTalker\Models\AiConversation;

class GetJobDescriptionTool implements AiToolHandlerContract
{
    public function __construct(private AiConversation $conversation) {}

    public function name(): string
    {
        return 'get_job_description';
    }

    public function description(): string
    {
        return 'Load the job description and any known job title or company name from the conversation context.';
    }

    public function schema(): array
    {
        return ['type' => 'object', 'properties' => (object) [], 'required' => []];
    }

    public function handle(array $input): array
    {
        $context = $this->conversation->context ?? [];

        return [
            'job_description' => $context['job_description'] ?? '',
            'job_title' => $context['job_title'] ?? null,
            'company_name' => $context['company_name'] ?? null,
        ];
    }
}
