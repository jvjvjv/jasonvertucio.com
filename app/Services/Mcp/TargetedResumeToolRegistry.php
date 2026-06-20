<?php

namespace App\Services\Mcp;

use App\Contracts\ResumeDataServiceContract;
use App\Services\TargetedResumeService;
use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolHandlerContract;
use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolRegistryContract;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Services\AiMemoryService;
use Jvjvjv\CodeTalker\Services\Mcp\DiscoversAiToolHandlers;
use Jvjvjv\CodeTalker\Services\Mcp\ToolResultConverter;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

class TargetedResumeToolRegistry implements AiToolRegistryContract
{
    use DiscoversAiToolHandlers;

    /** @var array<string, Tool|AiToolHandlerContract> */
    private array $handlers = [];

    public function __construct(
        AiConversation $conversation,
        ResumeDataServiceContract $resumeDataService,
        AiMemoryService $memoryService,
        TargetedResumeService $targetedResumeService,
    ) {
        $this->handlers = $this->discoverHandlers([
            app_path('Services/Mcp/Tools/TargetedResume') => 'App\\Services\\Mcp\\Tools\\TargetedResume\\',
        ], [
            // New canonical context for laravel/mcp Tool subclasses.
            'context' => ToolContext::forConversation($conversation),
            // Retained for backward compatibility with legacy AiToolHandlerContract tools.
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
            static function (object $handler): array {
                if ($handler instanceof Tool) {
                    $serialized = $handler->toArray();

                    return [
                        'name' => $serialized['name'],
                        'description' => (string) ($serialized['description'] ?? ''),
                        'input_schema' => $serialized['inputSchema'] ?? ['type' => 'object', 'properties' => (object) []],
                    ];
                }

                /** @var AiToolHandlerContract $handler */
                return [
                    'name' => $handler->name(),
                    'description' => $handler->description(),
                    'input_schema' => $handler->schema(),
                ];
            },
            $this->handlers,
        ));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function dispatch(string $toolName, array $input): array
    {
        if (! isset($this->handlers[$toolName])) {
            return ['error' => "Unknown tool: {$toolName}"];
        }

        $handler = $this->handlers[$toolName];

        if ($handler instanceof Tool) {
            return ToolResultConverter::toArray($handler->handle(new Request($input)));
        }

        return $handler->handle($input);
    }
}
