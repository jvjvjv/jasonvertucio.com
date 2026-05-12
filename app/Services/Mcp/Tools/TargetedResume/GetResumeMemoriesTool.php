<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Contracts\Mcp\AiToolHandlerContract;
use App\Services\AiMemoryService;

class GetResumeMemoriesTool implements AiToolHandlerContract
{
    public function __construct(
        private AiMemoryService $memoryService,
        private ?int $userId,
    ) {}

    public function name(): string
    {
        return 'get_resume_memories';
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
