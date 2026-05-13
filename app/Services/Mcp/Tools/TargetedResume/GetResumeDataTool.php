<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Contracts\Mcp\AiToolHandlerContract;
use App\Contracts\ResumeDataServiceContract;

class GetResumeDataTool implements AiToolHandlerContract
{
    public function __construct(private ResumeDataServiceContract $resumeDataService) {}

    public function name(): string
    {
        return 'get_resume_data';
    }

    public function schema(): array
    {
        return ['type' => 'object', 'properties' => (object) [], 'required' => []];
    }

    public function handle(array $input): array
    {
        return $this->resumeDataService->getAllEditableData();
    }
}
