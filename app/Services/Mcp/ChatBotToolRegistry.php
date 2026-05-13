<?php

namespace App\Services\Mcp;

use App\Contracts\Mcp\AiToolHandlerContract;
use App\Contracts\Mcp\AiToolRegistryContract;
use App\Contracts\ResumeDataServiceContract;
use App\Services\Mcp\Tools\ChatBot\GetRecentBlogPostsTool;
use App\Services\Mcp\Tools\ChatBot\GetResumeDataTool;
use App\Services\Mcp\Tools\ChatBot\GetSiteInfoTool;

class ChatBotToolRegistry implements AiToolRegistryContract
{
    /** @var array<string, AiToolHandlerContract> */
    private array $handlers;

    public function __construct(ResumeDataServiceContract $resumeDataService)
    {
        $handlers = [
            new GetResumeDataTool($resumeDataService),
            new GetRecentBlogPostsTool(),
            new GetSiteInfoTool(),
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
