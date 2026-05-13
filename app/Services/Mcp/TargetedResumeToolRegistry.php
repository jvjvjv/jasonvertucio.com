<?php

namespace App\Services\Mcp;

use App\Contracts\Mcp\AiToolHandlerContract;
use App\Contracts\Mcp\AiToolRegistryContract;
use App\Contracts\ResumeDataServiceContract;
use App\Models\AiConversation;
use App\Services\AiMemoryService;
use App\Services\TargetedResumeService;

class TargetedResumeToolRegistry implements AiToolRegistryContract
{
    use DiscoversAiToolHandlers;

    /** @var array<string, AiToolHandlerContract> */
    private array $handlers = [];

    public function __construct(
        AiConversation $conversation,
        ResumeDataServiceContract $resumeDataService,
        AiMemoryService $memoryService,
        TargetedResumeService $targetedResumeService,
    ) {
        $this->handlers = $this->discoverHandlers([
            app_path('Services/Mcp/Tools/TargetedResume'),
        ], [
            'conversation' => $conversation,
            'resumeDataService' => $resumeDataService,
            'memoryService' => $memoryService,
            'targetedResumeService' => $targetedResumeService,
            'userId' => $conversation->user_id,
        ]);
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
