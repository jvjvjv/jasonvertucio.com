<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get-job-description')]
#[Description('Load the job description and any known job title or company name from the conversation context.')]
class GetJobDescriptionTool extends AuthorizedResumeTool
{
    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $context = $this->context->conversation?->context ?? [];

        return Response::structured([
            'job_description' => $context['job_description'] ?? '',
            'job_title' => $context['job_title'] ?? null,
            'company_name' => $context['company_name'] ?? null,
        ]);
    }
}
