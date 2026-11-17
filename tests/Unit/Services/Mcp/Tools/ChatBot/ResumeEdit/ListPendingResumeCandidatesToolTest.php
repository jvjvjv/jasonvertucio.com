<?php

namespace Tests\Unit\Services\Mcp\Tools\ChatBot\ResumeEdit;

use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Services\DatabaseResumeDataService;
use App\Services\DatabaseResumeVersionService;
use App\Services\Mcp\Tools\ChatBot\ResumeEdit\ListPendingResumeCandidatesTool;
use App\Services\Resume\Diff\ResumeSnapshotDiffer;
use App\Services\Resume\DocumentRenderer;
use App\Services\Resume\HtmlDocumentComposer;
use App\Services\Resume\MarkdownToHtmlConverter;
use App\Services\Resume\MarkdownToOpenXmlConverter;
use App\Services\Resume\PdfRenderer;
use App\Services\Resume\ResumeMarkdownComposer;
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
            new DatabaseResumeVersionService($dataService, new DocumentRenderer, new ResumeMarkdownComposer, new MarkdownToOpenXmlConverter, new MarkdownToHtmlConverter, new HtmlDocumentComposer, new PdfRenderer),
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
        $tool = new ListPendingResumeCandidatesTool($context, $this->candidateService(), new DatabaseResumeDataService, new ResumeSnapshotDiffer);

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

    private function reviewer(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');

        return $user;
    }

    /**
     * The live version as a draft snapshot, after `$edit` changes it.
     */
    private function candidateFrom(ResumeVersion $base, int $revision, ?callable $edit = null): ResumeEditCandidate
    {
        $snapshot = (new DatabaseResumeDataService)->getAllEditableData($base);

        if ($edit !== null) {
            $snapshot = $edit($snapshot);
        }

        return ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => $revision, 'snapshot' => $snapshot]);
    }

    public function test_each_candidate_describes_its_changes_against_the_live_version(): void
    {
        ResumeVersion::query()->update(['is_current' => false]);
        $base = $this->liveVersion();
        $base->experiences()->create(['job_title' => 'Engineer', 'company' => 'Acme', 'sort_order' => 0])
            ->bullets()->create(['content' => 'Led a team of 4 engineers', 'sort_order' => 0]);
        $base->skillCategories()->create(['group' => 'top', 'title' => 'Languages', 'sort_order' => 0])
            ->skills()->create(['name' => 'PHP', 'sort_order' => 0]);
        $base->refresh();

        $this->candidateFrom($base, 1, function (array $snapshot): array {
            $snapshot['experience'][0]['bullets'][0] = 'Led a team of 6 engineers';
            $snapshot['skills']['top'][0]['list'][] = 'Rust';

            return $snapshot;
        });
        $this->candidateFrom($base, 2);

        $result = $this->handle(ToolContext::forUser($this->reviewer()->id));
        [$edited, $identical] = $result['pending_candidates'];

        $this->assertSame(2, $edited['change_count']);
        $this->assertSame([
            ['section' => 'Skills', 'item' => 'Languages', 'field' => 'Skill', 'kind' => 'added', 'before' => null, 'after' => 'Rust', 'shown' => true],
            ['section' => 'Experience', 'item' => 'Acme — Engineer', 'field' => 'Bullet 1', 'kind' => 'modified', 'before' => 'Led a team of 4 engineers', 'after' => 'Led a team of 6 engineers', 'shown' => true],
        ], $edited['changes']);
        $this->assertSame(0, $identical['change_count']);
        $this->assertSame([], $identical['changes']);
    }

    public function test_a_salary_change_is_flagged_as_not_shown(): void
    {
        ResumeVersion::query()->update(['is_current' => false]);
        $base = $this->liveVersion();
        $base->experiences()->create(['job_title' => 'Engineer', 'company' => 'Acme', 'salary_start_amount' => 85000, 'salary_start_period' => 'per_year', 'sort_order' => 0]);
        $base->refresh();

        $this->candidateFrom($base, 1, function (array $snapshot): array {
            $snapshot['experience'][0]['salaryStart'] = ['amount' => 90000, 'period' => 'per_year'];

            return $snapshot;
        });

        $changes = $this->handle(ToolContext::forUser($this->reviewer()->id))['pending_candidates'][0]['changes'];

        $this->assertSame([['section' => 'Experience', 'item' => 'Acme — Engineer', 'field' => 'Salary (start)', 'kind' => 'modified', 'before' => '85,000 / year', 'after' => '90,000 / year', 'shown' => false]], $changes);
    }
}
