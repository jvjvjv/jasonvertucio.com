<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Jvjvjv\CodeTalker\Services\AiMemoryService;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get-resume-memories')]
#[Description('Load learned preferences and insights from previous sessions with this user.')]
class GetResumeMemoriesTool extends AuthorizedResumeTool
{
    public function __construct(
        ToolContext $context,
        private AiMemoryService $memoryService,
    ) {
        parent::__construct($context);
    }

    /**
     * @return array<string, Type>
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

        if ($this->context->userId === null) {
            return Response::structured(['memories' => '']);
        }

        $memories = $this->memoryService->getMemoriesForPrompt('targeted-resume', $this->context->userId);

        return Response::structured(['memories' => $memories]);
    }
}
