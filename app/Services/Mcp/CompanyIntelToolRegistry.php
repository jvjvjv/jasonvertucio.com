<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\Log;
use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolHandlerContract;
use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolRegistryContract;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Services\LaravelAi\BridgedTool;
use Jvjvjv\CodeTalker\Services\Mcp\ChatBotToolRegistry;
use Jvjvjv\CodeTalker\Services\Mcp\DiscoversAiToolHandlers;
use Jvjvjv\CodeTalker\Services\Mcp\ToolResultConverter;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Ai\Contracts\Tool as LaravelAiTool;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

/**
 * The intel session's tools: the host's Company Intel tools, plus whichever
 * of the AI system's granted tools (web fetching and the like) it has. The
 * intel tools win a name collision.
 */
class CompanyIntelToolRegistry implements AiToolRegistryContract
{
    use DiscoversAiToolHandlers;

    /** @var array<string, Tool|AiToolHandlerContract> */
    private array $handlers = [];

    public function __construct(private AiConversation $conversation)
    {
        $this->handlers = $this->discoverHandlers([
            app_path('Services/Mcp/Tools/CompanyIntel') => 'App\\Services\\Mcp\\Tools\\CompanyIntel\\',
        ], [
            'context' => ToolContext::forConversation($conversation),
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
     * The intel tools followed by the system's granted tools.
     *
     * @return array<int, LaravelAiTool>
     */
    public function toLaravelAiTools(?array $grantedTools = null): array
    {
        $tools = array_map(
            fn (array $tool): BridgedTool => new BridgedTool(
                $tool['name'],
                $tool['description'],
                (array) $tool['input_schema'],
                $this,
            ),
            $this->toApiTools(),
        );

        $granted = array_values(array_diff($grantedTools ?? [], array_keys($this->handlers)));

        if ($granted === []) {
            return $tools;
        }

        return [...$tools, ...(new ChatBotToolRegistry($this->conversation, $granted))->toLaravelAiTools()];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function dispatch(string $toolName, array $input): array
    {
        if (! isset($this->handlers[$toolName])) {
            Log::warning('company-intel.tool-registry: unknown tool', [
                'conversation_id' => $this->conversation->id,
                'tool' => $toolName,
            ]);

            return ['error' => "Unknown tool: {$toolName}"];
        }

        $handler = $this->handlers[$toolName];

        return $handler instanceof Tool
            ? ToolResultConverter::toArray($handler->handle(new Request($input)))
            : $handler->handle($input);
    }
}
