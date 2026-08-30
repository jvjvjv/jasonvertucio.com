<?php

namespace Tests\Unit\Services\Mcp\Tools\ChatBot\ResumeEdit;

use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Services\DatabaseResumeDataService;
use App\Services\DatabaseResumeVersionService;
use App\Services\Mcp\Tools\ChatBot\ResumeEdit\ListPendingResumeCandidatesTool;
use App\Services\Resume\ResumeSectionValidator;
use App\Services\ResumeEditCandidateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Services\Mcp\ToolResultConverter;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Tests\TestCase;

class ListPendingResumeCandidatesToolTest extends TestCase
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
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        $version->personalInfo()->create([
            'name' => 'Jason Vertucio',
            'title' => 'Engineer',
            'email' => 'jason@example.com',
        ]);

        return $version;
    }

    /**
     * @return array<string, mixed>
     */
    private function handle(ToolContext $context): array
    {
        $tool = new ListPendingResumeCandidatesTool($context, $this->candidateService());

        return ToolResultConverter::toArray($tool->handle(new Request([])));
    }

    public function test_it_rejects_anonymous_callers(): void
    {
        $this->liveVersion();

        $result = $this->handle(ToolContext::forUser(null));

        $this->assertArrayHasKey('error', $result);
    }

    public function test_it_rejects_users_without_edit_resume_permission(): void
    {
        $this->liveVersion();
        $user = User::factory()->create();

        $result = $this->handle(ToolContext::forUser($user->id));

        $this->assertArrayHasKey('error', $result);
    }

    public function test_it_lists_no_candidates_when_nothing_is_pending(): void
    {
        $this->liveVersion();
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');

        $result = $this->handle(ToolContext::forUser($user->id));

        $this->assertSame([], $result['pending_candidates']);
    }

    public function test_it_lists_pending_candidates_with_a_suggested_version(): void
    {
        $base = $this->liveVersion();
        $base->update(['version' => '2026.1.4']);
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');

        ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => 1]);
        ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => 2]);

        $result = $this->handle(ToolContext::forUser($user->id));

        $this->assertSame('2026.1.4', $result['resume_version']);
        $this->assertCount(2, $result['pending_candidates']);
        $this->assertSame(1, $result['pending_candidates'][0]['revision_number']);
        $this->assertSame(2, $result['pending_candidates'][1]['revision_number']);
        $this->assertSame('2026.1.5', $result['suggested_version']);
    }
}
