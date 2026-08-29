<?php

namespace App\Services\Mcp\Tools\ChatBot;

use App\Contracts\ResumeDataServiceContract;
use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
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
#[Description(
    "Load the candidate's full resume data (experience, skills, education, projects) before tailoring or editing. "
    .'Includes `resume_version` (the live resume version string) and `pending_revision_number` (the highest-revision '
    .'pending AI-drafted candidate for that version, or null if none exists — call update-resume-section to continue it).'
)]
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

        $liveVersion = ResumeVersion::current()->first();
        $resumeData['resume_version'] = $liveVersion?->version;

        $pendingCandidate = $liveVersion
            ? ResumeEditCandidate::query()
                ->where('base_resume_version_id', $liveVersion->id)
                ->pending()
                ->orderByDesc('revision_number')
                ->first()
            : null;

        $resumeData['pending_revision_number'] = $pendingCandidate?->revision_number;

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
