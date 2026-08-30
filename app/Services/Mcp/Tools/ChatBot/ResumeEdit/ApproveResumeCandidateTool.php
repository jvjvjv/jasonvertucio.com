<?php

namespace App\Services\Mcp\Tools\ChatBot\ResumeEdit;

use App\Models\ResumeVersion;
use App\Services\ResumeEditCandidateService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('approve-resume-candidate')]
#[Description(
    'Approve a pending AI-drafted resume revision, publishing it as the new live resume at the given version. Use '
    ."list-pending-resume-candidates first to find the revision_number and the suggested_version. `version` must "
    .'match YYYY.MAJOR.MINOR and be strictly greater than the live resume version. This permanently supersedes any '
    .'other pending revision for the same base version and cannot be undone.'
)]
class ApproveResumeCandidateTool extends AuthorizedResumeEditTool
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
        return [
            'revision_number' => $schema->integer()
                ->description('The pending revision to approve, from list-pending-resume-candidates.')
                ->required(),
            'version' => $schema->string()
                ->description('The version to publish this revision as, e.g. "2026.1.5". Must exceed the live resume version.')
                ->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($response = $this->guard()) {
            return $response;
        }

        $liveVersion = ResumeVersion::current()->first();

        if ($liveVersion === null) {
            return Response::error('No live resume version found.');
        }

        $revisionNumber = $request->integer('revision_number');
        $candidate = $this->candidateService->findCandidateByRevisionNumber($liveVersion, $revisionNumber);

        if ($candidate === null || $candidate->status !== 'pending') {
            return Response::error("No pending revision #{$revisionNumber} found for the live resume version.");
        }

        $version = (string) ($request->get('version') ?? '');

        try {
            $result = $this->candidateService->approve($candidate, (string) $this->context->userId, $version);
        } catch (InvalidArgumentException $exception) {
            return Response::error($exception->getMessage());
        }

        $this->recordApprovalMessage($candidate->ai_conversation_id, $revisionNumber, $version);

        if (isset($result['error'])) {
            return Response::error("Candidate approved, but document generation failed: {$result['error']}");
        }

        return Response::structured([
            'success' => true,
            'revision_number' => $revisionNumber,
            'version' => $version,
            '_page_reload' => true,
        ]);
    }

    private function recordApprovalMessage(?int $conversationId, int $revisionNumber, string $version): void
    {
        if ($conversationId === null) {
            return;
        }

        AiConversationMessage::create([
            'ai_conversation_id' => $conversationId,
            'role' => 'user',
            'content' => "I approved resume revision #{$revisionNumber} via the approve-resume-candidate tool, publishing it as version {$version}.",
            'metadata' => [
                'origin' => 'ai_resume_edit_approval',
                'revision_number' => $revisionNumber,
                'version' => $version,
            ],
        ]);
    }
}
