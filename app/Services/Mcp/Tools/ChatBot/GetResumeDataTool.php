<?php

namespace App\Services\Mcp\Tools\ChatBot;

use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolHandlerContract;
use App\Contracts\ResumeDataServiceContract;

class GetResumeDataTool implements AiToolHandlerContract
{
    public function __construct(private ResumeDataServiceContract $resumeDataService) {}

    public function name(): string
    {
        return 'get_resume_data';
    }

    public function description(): string
    {
        return "Load the candidate's full resume data (experience, skills, education, projects) before tailoring.";
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
