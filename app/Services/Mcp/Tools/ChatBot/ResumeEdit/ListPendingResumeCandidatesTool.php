<?php

namespace App\Services\Mcp\Tools\ChatBot\ResumeEdit;

use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use App\Services\ResumeEditCandidateService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('list-pending-resume-candidates')]
#[Description(
    'List every AI-drafted resume revision awaiting human review for the live resume version. Each entry includes '
    .'its revision number, last-edited time, and status. The response also includes `suggested_version`, the version '
    .'to pass to approve-resume-candidate for the common case of accepting the default patch bump.'
)]
class ListPendingResumeCandidatesTool extends AuthorizedResumeEditTool
{
    public function __construct(
        ToolContext $context,
        private ResumeEditCandidateService $candidateService,
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

        $liveVersion = ResumeVersion::current()->first();

        if ($liveVersion === null) {
            return Response::structured([
                'resume_version' => null,
                'pending_candidates' => [],
                'suggested_version' => null,
            ]);
        }

        $pending = ResumeEditCandidate::query()
            ->where('base_resume_version_id', $liveVersion->id)
            ->pending()
            ->orderBy('revision_number')
            ->get(['revision_number', 'status', 'last_edited_at']);

        return Response::structured([
            'resume_version' => $liveVersion->version,
            'pending_candidates' => $pending->map(fn (ResumeEditCandidate $candidate): array => [
                'revision_number' => $candidate->revision_number,
                'status' => $candidate->status,
                'last_edited_at' => $candidate->last_edited_at->toIso8601String(),
            ])->all(),
            'suggested_version' => $this->candidateService->suggestedNextVersion($liveVersion),
        ]);
    }
}
