<?php

namespace App\Services\Mcp\Tools\ChatBot;

use App\Contracts\ResumeDataServiceContract;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get-resume-data')]
#[Description("Load the candidate's full resume data (experience, skills, education, projects) before tailoring.")]
class GetResumeDataTool extends Tool
{
    public function __construct(
        private ToolContext $context,
        private ResumeDataServiceContract $resumeDataService,
    ) {}

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $resumeData = $this->resumeDataService->getAllEditableData();

        if (! $this->canViewSalary()) {
            $resumeData['experience'] = array_map(static function (array $experience): array {
                $experience['salaryStart'] = null;
                $experience['salaryEnd'] = null;

                return $experience;
            }, $resumeData['experience'] ?? []);
        }

        return Response::structured($resumeData);
    }

    private function canViewSalary(): bool
    {
        if ($this->context->userId === null) {
            return false;
        }

        return User::find($this->context->userId)?->can('save-resume') ?? false;
    }
}
