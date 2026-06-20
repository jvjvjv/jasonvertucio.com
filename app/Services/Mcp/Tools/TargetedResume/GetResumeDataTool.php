<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Contracts\ResumeDataServiceContract;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get-resume-data')]
#[Description("Load the candidate's full resume data (experience, skills, education, projects) before tailoring.")]
class GetResumeDataTool extends AuthorizedResumeTool
{
    public function __construct(
        ToolContext $context,
        private ResumeDataServiceContract $resumeDataService,
    ) {
        parent::__construct($context);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
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

        return Response::structured($this->resumeDataService->getAllEditableData());
    }
}
