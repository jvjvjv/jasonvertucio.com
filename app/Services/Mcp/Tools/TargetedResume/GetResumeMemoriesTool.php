<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolHandlerContract;
use Jvjvjv\CodeTalker\Services\AiMemoryService;

class GetResumeMemoriesTool implements AiToolHandlerContract
{
    public function __construct(
        private AiMemoryService $memoryService,
        private string|int|null $userId,
    ) {}

    public function name(): string
    {
        return 'get_resume_memories';
    }

    public function description(): string
    {
        return 'Load learned preferences and insights from previous sessions with this user.';
    }

    public function schema(): array
    {
        return ['type' => 'object', 'properties' => (object) [], 'required' => []];
    }

    public function handle(array $input): array
    {
        if ($this->userId === null) {
            return ['memories' => ''];
        }

        $memories = $this->memoryService->getMemoriesForPrompt('targeted-resume', $this->userId);

        return ['memories' => $memories];
    }
}
