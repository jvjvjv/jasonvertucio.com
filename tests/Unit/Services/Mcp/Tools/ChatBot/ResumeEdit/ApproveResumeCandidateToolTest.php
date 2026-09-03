<?php

namespace Tests\Unit\Services\Mcp\Tools\ChatBot\ResumeEdit;

use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Services\DatabaseResumeDataService;
use App\Services\DatabaseResumeVersionService;
use App\Services\Mcp\Tools\ChatBot\ResumeEdit\ApproveResumeCandidateTool;
use App\Services\Resume\ResumeSectionValidator;
use App\Services\ResumeEditCandidateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Models\AiConversation;
use Jvjvjv\CodeTalker\Models\AiConversationMessage;
use Jvjvjv\CodeTalker\Services\Mcp\ToolResultConverter;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Tests\TestCase;

class ApproveResumeCandidateToolTest extends TestCase
{
    use DatabaseTransactions;

    private function candidateService(): ResumeEditCandidateService
    {
        $dataService = new DatabaseResumeDataService;

        return new ResumeEditCandidateService(
            $dataService,
            new DatabaseResumeVersionService($dataService),
            new ResumeSectionValidator,
        );
    }

    private function liveVersion(): ResumeVersion
    {
        $version = ResumeVersion::factory()->create(['is_current' => true, 'version' => '2026.1.4']);
        $version->personalInfo()->create([
            'name' => 'Jason Vertucio',
            'title' => 'Engineer',
            'email' => 'jason@example.com',
        ]);
        $version->experiences()->create(['job_title' => 'Engineer', 'company' => 'Acme', 'sort_order' => 0]);
        $version->educations()->create(['institution' => 'State University', 'sort_order' => 0]);
        $version->projects()->create(['project_name' => 'Side Project', 'sort_order' => 0]);

        return $version;
    }

    private function pendingCandidateFor(ResumeVersion $base, int $revisionNumber = 1): ResumeEditCandidate
    {
        return ResumeEditCandidate::factory()->create([
            'base_resume_version_id' => $base->id,
            'revision_number' => $revisionNumber,
            'snapshot' => [
                'personal' => ['name' => 'Approved Name', 'title' => 'Engineer', 'email' => 'jason@example.com'],
                'skills' => ['top' => [], 'other' => []],
                'experience' => [['jobTitle' => 'Engineer', 'company' => 'Acme', 'bullets' => []]],
                'education' => [['institution' => 'State University']],
                'projects' => [['projectName' => 'Side Project', 'bullets' => []]],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function handle(ToolContext $context, array $input): array
    {
        $tool = new ApproveResumeCandidateTool($context, $this->candidateService());

        return ToolResultConverter::toArray($tool->handle(new Request($input)));
    }

    public function test_it_rejects_anonymous_callers(): void
    {
        $base = $this->liveVersion();
        $this->pendingCandidateFor($base);

        $result = $this->handle(ToolContext::forUser(null), ['revision_number' => 1, 'version' => '2026.1.5']);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('pending', ResumeEditCandidate::first()->status);
    }

    public function test_it_rejects_users_without_edit_resume_permission(): void
    {
        $base = $this->liveVersion();
        $this->pendingCandidateFor($base);
        $user = User::factory()->create();

        $result = $this->handle(ToolContext::forUser($user->id), ['revision_number' => 1, 'version' => '2026.1.5']);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('pending', ResumeEditCandidate::first()->status);
    }

    public function test_authorized_user_approves_a_pending_candidate(): void
    {
        $base = $this->liveVersion();
        $this->pendingCandidateFor($base);
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');

        $result = $this->handle(ToolContext::forUser($user->id), ['revision_number' => 1, 'version' => '2026.1.5']);

        $this->assertTrue($result['success']);
        $this->assertSame('approved', ResumeEditCandidate::first()->status);
        $this->assertSame('2026.1.5', ResumeVersion::current()->first()->version);
        $this->assertSame('Approved Name', ResumeVersion::current()->first()->personalInfo->name);
    }

    public function test_it_rejects_an_unknown_revision_number(): void
    {
        $base = $this->liveVersion();

        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');

        $result = $this->handle(ToolContext::forUser($user->id), ['revision_number' => 99, 'version' => '2026.1.5']);

        $this->assertArrayHasKey('error', $result);
        $this->assertTrue($base->fresh()->is_current);
    }

    public function test_it_rejects_a_version_that_does_not_exceed_the_live_version(): void
    {
        $base = $this->liveVersion();
        $this->pendingCandidateFor($base);
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');

        $result = $this->handle(ToolContext::forUser($user->id), ['revision_number' => 1, 'version' => '2026.1.4']);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame('pending', ResumeEditCandidate::first()->status);
        $this->assertTrue($base->fresh()->is_current);
    }

    public function test_successful_approval_records_a_tagged_conversation_message(): void
    {
        $base = $this->liveVersion();
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');
        $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
        $candidate = $this->pendingCandidateFor($base);
        $candidate->update(['ai_conversation_id' => $conversation->id]);

        $result = $this->handle(ToolContext::forConversation($conversation), ['revision_number' => 1, 'version' => '2026.1.5']);

        $this->assertTrue($result['success']);

        $message = AiConversationMessage::where('ai_conversation_id', $conversation->id)->latest('id')->first();
        $this->assertNotNull($message);
        // The persona ran the approval, so the note belongs to its side of the
        // transcript — see UpdateResumeSectionToolTest for the same reasoning.
        $this->assertSame('assistant', $message->role);
        $this->assertSame('ai_resume_edit_approval', $message->metadata['origin']);
    }
}
