<?php

namespace App\Services\Mcp\Tools\ChatBot\ResumeEdit;

use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use App\Services\DatabaseResumeDataService;
use App\Services\Resume\Diff\ResumeSnapshotDiffer;
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
    .'its revision number, last-edited time, and status. Each candidate also includes `changes` compared with the live '
    .'resume (section, item, field, kind, before, after, and `shown`, false for fields the resume does not display) '
    .'and `change_count`, so you can tell the user what a revision changes before approving it. The response also '
    .'includes `suggested_version`, the version to pass to approve-resume-candidate for the common case of accepting '
    .'the default patch bump.'
)]
class ListPendingResumeCandidatesTool extends AuthorizedResumeEditTool
{
    public function __construct(
        ToolContext $context,
        private ResumeEditCandidateService $candidateService,
        private DatabaseResumeDataService $resumeData,
        private ResumeSnapshotDiffer $differ,
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
            ->get(['revision_number', 'status', 'last_edited_at', 'snapshot']);

        $liveData = $pending->isNotEmpty() ? $this->resumeData->getAllEditableData($liveVersion) : [];

        return Response::structured([
            'resume_version' => $liveVersion->version,
            'pending_candidates' => $pending->map(function (ResumeEditCandidate $candidate) use ($liveData): array {
                $changes = $this->changes($liveData, $candidate);

                return [
                    'revision_number' => $candidate->revision_number,
                    'status' => $candidate->status,
                    'last_edited_at' => $candidate->last_edited_at->toIso8601String(),
                    'change_count' => count($changes),
                    'changes' => $changes,
                ];
            })->all(),
            'suggested_version' => $this->candidateService->suggestedNextVersion($liveVersion),
        ]);
    }

    /**
     * What the candidate changes compared with the live version, as plain
     * values: the model needs the text, not the word-level markup.
     *
     * @param  array<mixed>  $liveData
     * @return list<array{section: string, item: string, field: string, kind: string, before: ?string, after: ?string, shown: bool}>
     */
    private function changes(array $liveData, ResumeEditCandidate $candidate): array
    {
        $diff = $this->differ->diff($liveData, is_array($candidate->snapshot) ? $candidate->snapshot : []);

        return array_map(fn (array $change): array => [
            'section' => $change['section'],
            'item' => $change['item'],
            'field' => $change['field'],
            'kind' => $change['kind'],
            'before' => $change['before'],
            'after' => $change['after'],
            'shown' => $change['shown'],
        ], $diff->changes());
    }
}
