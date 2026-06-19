<?php

namespace App\Services\Mcp;

use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolHandlerContract;
use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolRegistryContract;
use App\Contracts\ResumeDataServiceContract;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Services\AiMemoryService;
use App\Services\TargetedResumeService;

class ChatBotToolRegistry implements AiToolRegistryContract
{
    use DiscoversAiToolHandlers;

    /** @var array<string, AiToolHandlerContract> */
    private array $handlers = [];

    public function __construct(
        AiConversation $conversation,
        ResumeDataServiceContract $resumeDataService,
        AiMemoryService $memoryService,
        TargetedResumeService $targetedResumeService,
        ?array $allowedToolNames = null,
        bool $exposeAllDiscoveredTools = false,
    )
    {
        $handlers = $this->discoverHandlers(
            [app_path('Services/Mcp/Tools')],
            [
                'conversation' => $conversation,
                'resumeDataService' => $resumeDataService,
                'memoryService' => $memoryService,
                'targetedResumeService' => $targetedResumeService,
                'userId' => $conversation->user_id,
            ],
            ['Services/Mcp/Tools/ChatBot'],
        );

        if ($exposeAllDiscoveredTools) {
            $this->handlers = $handlers;

            return;
        }

        if ($allowedToolNames === null || $allowedToolNames === []) {
            $this->handlers = [];

            return;
        }

        $allowedToolNames = array_values(array_unique(array_map('strval', $allowedToolNames)));
        $allowedLookup = array_fill_keys($allowedToolNames, true);

        $this->handlers = array_filter(
            $handlers,
            static fn (AiToolHandlerContract $handler, string $name): bool => isset($allowedLookup[$name]),
            ARRAY_FILTER_USE_BOTH,
        );
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
