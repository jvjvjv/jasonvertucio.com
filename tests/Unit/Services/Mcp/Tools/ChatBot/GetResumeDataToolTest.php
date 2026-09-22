<?php

namespace Tests\Unit\Services\Mcp\Tools\ChatBot;

use App\Contracts\ResumeDataServiceContract;
use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Services\DatabaseResumeDataService;
use App\Services\DatabaseResumeVersionService;
use App\Services\Resume\DocumentRenderer;
use App\Services\Resume\HtmlDocumentComposer;
use App\Services\Resume\MarkdownToHtmlConverter;
use App\Services\Resume\MarkdownToOpenXmlConverter;
use App\Services\Resume\PdfRenderer;
use App\Services\Resume\ResumeMarkdownComposer;
use App\Services\Mcp\Tools\ChatBot\GetResumeDataTool;
use App\Services\Resume\ResumeSectionValidator;
use App\Services\ResumeEditCandidateService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jvjvjv\CodeTalker\Services\Mcp\ToolResultConverter;
use Jvjvjv\CodeTalker\Support\ToolContext;
use Laravel\Mcp\Request;
use Mockery;
use Tests\TestCase;

class GetResumeDataToolTest extends TestCase
{
    use DatabaseTransactions;

    private function service(): ResumeDataServiceContract
    {
        $mock = Mockery::mock(ResumeDataServiceContract::class);
        $mock->shouldReceive('getAllEditableData')->andReturn([
            'experience' => [[
                'company' => 'Acme',
                'salaryStart' => ['amount' => 120000.0, 'period' => 'per_year'],
                'salaryEnd' => ['amount' => 150000.0, 'period' => 'per_year'],
            ]],
        ]);

        return $mock;
    }

    private function candidateService(): ResumeEditCandidateService
    {
        $dataService = new DatabaseResumeDataService;

        return new ResumeEditCandidateService(
            $dataService,
            new DatabaseResumeVersionService($dataService, new DocumentRenderer, new ResumeMarkdownComposer, new MarkdownToOpenXmlConverter, new MarkdownToHtmlConverter, new HtmlDocumentComposer, new PdfRenderer),
            new ResumeSectionValidator,
        );
    }

    /**
     * Someone entitled to see unpublished draft revisions.
     *
     * Draft visibility is gated on `edit-resume`: these fields describe work in
     * progress, and a caller who may not change a draft may not read one.
     */
    private function reviewerContext(): ToolContext
    {
        Permission::firstOrCreate(['name' => 'edit-resume']);

        $user = User::factory()->create();
        $user->givePermissionTo('edit-resume');

        return ToolContext::forUser($user->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function handle(ToolContext $context, array $input = []): array
    {
        $tool = new GetResumeDataTool($context, $this->service(), $this->candidateService());

        return ToolResultConverter::toArray($tool->handle(new Request($input)));
    }

    public function test_it_redacts_salary_for_anonymous_callers(): void
    {
        $result = $this->handle(ToolContext::forUser(null));

        $this->assertNull($result['experience'][0]['salaryStart']);
        $this->assertNull($result['experience'][0]['salaryEnd']);
    }

    public function test_it_redacts_salary_for_users_without_save_resume_permission(): void
    {
        $user = User::factory()->create();

        $result = $this->handle(ToolContext::forUser($user->id));

        $this->assertNull($result['experience'][0]['salaryStart']);
        $this->assertNull($result['experience'][0]['salaryEnd']);
    }

    public function test_it_exposes_salary_for_users_with_save_resume_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('save-resume');

        $result = $this->handle(ToolContext::forUser($user->id));

        $this->assertSame(['amount' => 120000.0, 'period' => 'per_year'], $result['experience'][0]['salaryStart']);
        $this->assertSame(['amount' => 150000.0, 'period' => 'per_year'], $result['experience'][0]['salaryEnd']);
    }

    public function test_it_reports_the_live_version_and_no_pending_revision_when_none_exists(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);

        $result = $this->handle($this->reviewerContext());

        $this->assertSame($version->version, $result['resume_version']);
        $this->assertNull($result['pending_revision_number']);
    }

    public function test_it_reports_the_highest_pending_revision_number(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        ResumeEditCandidate::factory()->create(['base_resume_version_id' => $version->id, 'revision_number' => 1]);
        ResumeEditCandidate::factory()->create(['base_resume_version_id' => $version->id, 'revision_number' => 2]);

        $result = $this->handle($this->reviewerContext());

        $this->assertSame(2, $result['pending_revision_number']);
    }

    public function test_it_loads_a_specific_requested_revision_instead_of_live_data(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        ResumeEditCandidate::factory()->create([
            'base_resume_version_id' => $version->id,
            'revision_number' => 1,
            'status' => 'pending',
            'snapshot' => ['personal' => ['name' => 'Draft Name', 'title' => 'x', 'email' => 'a@b.com']],
        ]);

        $result = $this->handle($this->reviewerContext(), ['revision_number' => 1]);

        $this->assertTrue($result['requested_revision_found']);
        $this->assertSame(1, $result['viewing_revision_number']);
        $this->assertSame('pending', $result['viewing_revision_status']);
        $this->assertSame('Draft Name', $result['personal']['name']);
    }

    public function test_it_reports_a_missing_requested_revision_and_falls_back_to_live_data(): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);

        $result = $this->handle($this->reviewerContext(), ['revision_number' => 999]);

        $this->assertFalse($result['requested_revision_found']);
        $this->assertArrayNotHasKey('viewing_revision_number', $result);
        $this->assertArrayHasKey('experience', $result);
    }

    public function test_it_tells_an_anonymous_caller_nothing_about_pending_revisions(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        ResumeEditCandidate::factory()->create(['base_resume_version_id' => $version->id, 'revision_number' => 1]);

        $result = $this->handle(ToolContext::forUser(null));

        $this->assertArrayNotHasKey('pending_revision_number', $result);
    }

    public function test_an_anonymous_revision_request_returns_live_data_without_confirming_the_revision(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        ResumeEditCandidate::factory()->create([
            'base_resume_version_id' => $version->id,
            'revision_number' => 1,
            'status' => 'pending',
            'snapshot' => ['personal' => ['name' => 'Draft Name', 'title' => 'x', 'email' => 'a@b.com']],
        ]);

        $result = $this->handle(ToolContext::forUser(null), ['revision_number' => 1]);

        $this->assertArrayNotHasKey('requested_revision_found', $result);
        $this->assertArrayNotHasKey('viewing_revision_number', $result);
        $this->assertArrayNotHasKey('viewing_revision_status', $result);
        $this->assertArrayHasKey('experience', $result);
    }
}
