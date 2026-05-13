<?php

namespace App\Services\Mcp;

use App\Contracts\Mcp\AiToolHandlerContract;
use App\Contracts\Mcp\AiToolRegistryContract;
use App\Contracts\ResumeDataServiceContract;
use App\Models\AiConversation;
use App\Services\AiMemoryService;
use App\Services\Mcp\Tools\TargetedResume\GetJobDescriptionTool;
use App\Services\Mcp\Tools\TargetedResume\GetResumeDataTool;
use App\Services\Mcp\Tools\TargetedResume\GetResumeMemoriesTool;
use App\Services\Mcp\Tools\TargetedResume\MarkAppliedTool;
use App\Services\Mcp\Tools\TargetedResume\SaveCoverLetterTool;
use App\Services\Mcp\Tools\TargetedResume\SaveTailoredResumeTool;
use App\Services\Mcp\Tools\TargetedResume\UpdateFitAssessmentTool;
use App\Services\TargetedResumeService;

class TargetedResumeToolRegistry implements AiToolRegistryContract
{
    /** @var array<string, AiToolHandlerContract> */
    private array $handlers;

    public function __construct(
        AiConversation $conversation,
        ResumeDataServiceContract $resumeDataService,
        AiMemoryService $memoryService,
        TargetedResumeService $targetedResumeService,
    ) {
        $handlers = [
            new GetResumeDataTool($resumeDataService),
            new GetJobDescriptionTool($conversation),
            new GetResumeMemoriesTool($memoryService, $conversation->user_id),
            new UpdateFitAssessmentTool($conversation),
            new SaveTailoredResumeTool($conversation, $targetedResumeService),
            new SaveCoverLetterTool($conversation, $targetedResumeService),
            new MarkAppliedTool($conversation),
        ];

        foreach ($handlers as $handler) {
            $this->handlers[$handler->name()] = $handler;
        }
    }

    /**
     * @return array<int, array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    public function toApiTools(): array
    {
        return array_values(array_map(
            static fn (AiToolHandlerContract $handler): array => [
                'name' => $handler->name(),
                'description' => $handler->description(),
                'input_schema' => $handler->schema(),
            ],
            $this->handlers,
        ));
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function dispatch(string $toolName, array $input): array
    {
        if (!isset($this->handlers[$toolName])) {
            return ['error' => "Unknown tool: {$toolName}"];
        }

        return $this->handlers[$toolName]->handle($input);
    }
}
