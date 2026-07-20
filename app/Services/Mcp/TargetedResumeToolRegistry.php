<?php

namespace App\Services\Mcp;

use App\Contracts\ResumeDataServiceContract;
use App\Services\TargetedResumeService;
use Illuminate\Support\Facades\Log;
use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolHandlerContract;
use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolRegistryContract;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Services\AiMemoryService;
use Jvjvjv\CodeTalker\Services\LaravelAi\BridgedTool;
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

    private int $conversationId;

    private bool $pageReloadRequested = false;

    public function __construct(
        AiConversation $conversation,
        ResumeDataServiceContract $resumeDataService,
        AiMemoryService $memoryService,
        TargetedResumeService $targetedResumeService,
    ) {
        $this->conversationId = (int) $conversation->id;

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
     * The registered tools adapted to laravel/ai's Tool contract, for use in
     * a laravel/ai agent's tools() list.
     *
     * @return array<int, BridgedTool>
     */
    public function toLaravelAiTools(): array
    {
        return array_map(
            fn (array $tool): BridgedTool => new BridgedTool(
                $tool['name'],
                $tool['description'],
                (array) $tool['input_schema'],
                $this,
            ),
            $this->toApiTools(),
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function dispatch(string $toolName, array $input): array
    {
        Log::info('targeted-resume.tool-registry: dispatch requested', [
            'conversation_id' => $this->conversationId,
            'tool' => $toolName,
            'input_keys' => array_keys($input),
        ]);

        if (! isset($this->handlers[$toolName])) {
            Log::warning('targeted-resume.tool-registry: unknown tool', [
                'conversation_id' => $this->conversationId,
                'tool' => $toolName,
                'known_tools' => array_keys($this->handlers),
            ]);

            return ['error' => "Unknown tool: {$toolName}"];
        }

        $handler = $this->handlers[$toolName];

        if ($handler instanceof Tool) {
            $result = $this->capturePageReload(
                ToolResultConverter::toArray($handler->handle(new Request($input))),
            );

            Log::info('targeted-resume.tool-registry: dispatch completed', [
                'conversation_id' => $this->conversationId,
                'tool' => $toolName,
                'result_keys' => array_keys($result),
            ]);

            return $result;
        }

        $result = $this->capturePageReload($handler->handle($input));

        Log::info('targeted-resume.tool-registry: dispatch completed', [
            'conversation_id' => $this->conversationId,
            'tool' => $toolName,
            'result_keys' => array_keys($result),
        ]);

        return $result;
    }

    /**
     * Tool results carry `_page_reload` to tell the browser to refresh. Under
     * laravel/ai the agent loop consumes tool results internally, so the flag
     * is latched here and drained by the streaming caller instead of being
     * yielded inline. The key is stripped so it never reaches the model.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function capturePageReload(array $result): array
    {
        if (! empty($result['_page_reload'])) {
            $this->pageReloadRequested = true;
        }

        unset($result['_page_reload']);

        return $result;
    }

    /**
     * Whether a tool requested a page reload since this was last called.
     * Reading it clears the flag.
     */
    public function consumePageReload(): bool
    {
        $requested = $this->pageReloadRequested;
        $this->pageReloadRequested = false;

        return $requested;
    }
}
