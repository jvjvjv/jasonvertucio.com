<?php

namespace App\Services\Mcp\Tools\ChatBot;

use App\Contracts\ResumeDataServiceContract;
use App\Models\User;
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
use Laravel\Mcp\Server\Tool;

#[Name('get-resume-data')]
#[Description(
    "Load the candidate's full resume data (experience, skills, education, projects) before tailoring or editing. "
    .'Includes `resume_version` (the live resume version string) and `pending_revision_number` (the highest-revision '
    .'pending AI-drafted candidate for that version, or null if none exists — tell the user a revision is already in '
    .'progress if this is set, and call update-resume-section to continue it rather than starting a new one). Pass '
    .'`revision_number` to load that specific draft revision\'s data instead of the live resume, e.g. to review what '
    .'a pending revision actually contains.'
)]
class GetResumeDataTool extends Tool
{
    use LoadsResumeDataWithRevisionInfo;

    public function __construct(
        private ToolContext $context,
        private ResumeDataServiceContract $resumeDataService,
        private ResumeEditCandidateService $candidateService,
    ) {}

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
        $requestedRevisionNumber = $request->filled('revision_number') ? $request->integer('revision_number') : null;

        $resumeData = $this->loadResumeDataWithRevisionInfo($this->resumeDataService, $this->candidateService, $requestedRevisionNumber);

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
