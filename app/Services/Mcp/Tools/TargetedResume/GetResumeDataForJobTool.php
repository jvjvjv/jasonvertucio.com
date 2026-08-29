<?php

namespace App\Services\Mcp\Tools\TargetedResume;

use App\Contracts\ResumeDataServiceContract;
use App\Services\Mcp\Tools\Concerns\LoadsResumeDataWithRevisionInfo;
use App\Services\ResumeEditCandidateService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('get-resume-data-for-job')]
#[Description(
    "Load the candidate's full base resume data (experience, skills, education, projects) before tailoring it for a "
    .'specific job. Includes `resume_version` (the live resume version string) and `pending_revision_number` (an '
    .'AI-drafted revision of the *main* resume pending review, if any — mention this to the user since it means the '
    .'base resume they\'re about to tailor from is not final). Pass `revision_number` to load that specific draft '
    .'revision\'s data instead of the live resume.'
)]
class GetResumeDataForJobTool extends AuthorizedResumeTool
{
    use LoadsResumeDataWithRevisionInfo;

    public function __construct(
        ToolContext $context,
        private ResumeDataServiceContract $resumeDataService,
        private ResumeEditCandidateService $candidateService,
    ) {
        parent::__construct($context);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'revision_number' => $schema->integer()
                ->description('Load this specific pending revision\'s data instead of the live resume.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $requestedRevisionNumber = $request->filled('revision_number') ? $request->integer('revision_number') : null;

        $resumeData = $this->loadResumeDataWithRevisionInfo($this->resumeDataService, $this->candidateService, $requestedRevisionNumber);

        return Response::structured($resumeData);
    }
}
