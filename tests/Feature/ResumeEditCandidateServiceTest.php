<?php

namespace Tests\Feature;

use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Services\DatabaseResumeDataService;
use App\Services\DatabaseResumeVersionService;
use App\Services\Resume\ResumeSectionValidator;
use App\Services\ResumeEditCandidateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class ResumeEditCandidateServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected ResumeEditCandidateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $dataService = new DatabaseResumeDataService;
        $versionService = new DatabaseResumeVersionService($dataService);

        $this->service = new ResumeEditCandidateService($dataService, $versionService, new ResumeSectionValidator);
    }

    private function liveVersion(): ResumeVersion
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
        $version->personalInfo()->create([
            'name' => 'Jason Vertucio',
            'title' => 'Engineer',
            'email' => 'jason@example.com',
        ]);
        $version->experiences()->create([
            'job_title' => 'Engineer',
            'company' => 'Acme',
            'sort_order' => 0,
        ]);
        $version->educations()->create([
            'institution' => 'State University',
            'sort_order' => 0,
        ]);
        $version->projects()->create([
            'project_name' => 'Side Project',
            'sort_order' => 0,
        ]);

        return $version;
    }

    public function test_first_edit_creates_revision_one_seeded_from_live_version(): void
    {
        $base = $this->liveVersion();

        $candidate = $this->service->resolveOrCreateCandidateForEdit($base, null);

        $this->assertSame(1, $candidate->revision_number);
        $this->assertSame('pending', $candidate->status);
        $this->assertSame('Jason Vertucio', $candidate->snapshot['personal']['name']);
    }

    public function test_second_edit_within_window_reuses_the_same_candidate(): void
    {
        $base = $this->liveVersion();

        $first = $this->service->resolveOrCreateCandidateForEdit($base, null);
        $again = $this->service->resolveOrCreateCandidateForEdit($base, null);

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, ResumeEditCandidate::count());
    }

    public function test_edit_after_window_elapses_creates_a_new_revision_seeded_from_prior_and_leaves_it_pending(): void
    {
        config(['resume.ai_edit_batch_window_hours' => 12]);
        $base = $this->liveVersion();

        $first = $this->service->resolveOrCreateCandidateForEdit($base, null);
        $this->service->applySectionEdit($first, 'personal', ['name' => 'Edited Name', 'title' => 'Engineer', 'email' => 'jason@example.com']);

        $this->travel(13)->hours();

        $second = $this->service->resolveOrCreateCandidateForEdit($base, null);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, $second->revision_number);
        $this->assertSame('Edited Name', $second->snapshot['personal']['name']);

        $this->assertSame('pending', $first->fresh()->status);
        $this->assertSame(2, ResumeEditCandidate::where('base_resume_version_id', $base->id)->pending()->count());
    }

    public function test_edit_after_approval_branches_from_the_new_live_version(): void
    {
        $base = $this->liveVersion();
        $approver = User::factory()->create();

        $candidate = $this->service->resolveOrCreateCandidateForEdit($base, null);
        $this->service->applySectionEdit($candidate, 'personal', ['name' => 'Approved Name', 'title' => 'Engineer', 'email' => 'jason@example.com']);

        $this->service->approve($candidate, $approver->id, $this->service->suggestedNextVersion($base));

        $newLive = ResumeVersion::current()->first();
        $this->assertNotSame($base->id, $newLive->id);

        $next = $this->service->resolveOrCreateCandidateForEdit($newLive, null);
        $this->assertSame(1, $next->revision_number);
        $this->assertSame('Approved Name', $next->snapshot['personal']['name']);
    }

    public function test_approving_a_candidate_rejects_all_other_pending_candidates_for_the_same_base(): void
    {
        $base = $this->liveVersion();
        $approver = User::factory()->create();

        $keep = $this->service->resolveOrCreateCandidateForEdit($base, null);
        $siblingA = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => $keep->revision_number + 1]);
        $siblingB = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => $keep->revision_number + 2]);
        $otherBaseCandidate = ResumeEditCandidate::factory()->create(['revision_number' => 1]);

        $this->service->approve($keep, $approver->id, $this->service->suggestedNextVersion($base));

        $this->assertSame('approved', $keep->fresh()->status);
        $this->assertDatabaseMissing('resume_edit_candidates', ['id' => $siblingA->id]);
        $this->assertDatabaseMissing('resume_edit_candidates', ['id' => $siblingB->id]);
        $this->assertDatabaseHas('resume_edit_candidates', ['id' => $otherBaseCandidate->id]);
    }

    public function test_approve_materializes_snapshot_bumps_version_and_marks_candidate_approved(): void
    {
        $base = $this->liveVersion();
        $approver = User::factory()->create();

        $candidate = $this->service->resolveOrCreateCandidateForEdit($base, null);
        $this->service->applySectionEdit($candidate, 'personal', ['name' => 'New Name', 'title' => 'Engineer', 'email' => 'jason@example.com']);

        $this->service->approve($candidate, $approver->id, $this->service->suggestedNextVersion($base));

        $candidate->refresh();
        $this->assertSame('approved', $candidate->status);
        $this->assertSame($approver->id, $candidate->approved_by_user_id);
        $this->assertNotNull($candidate->approved_at);

        $newLive = ResumeVersion::current()->first();
        $this->assertFalse($base->fresh()->is_current);
        $this->assertTrue($newLive->is_current);
        $this->assertSame('New Name', $newLive->personalInfo->name);
    }

    public function test_only_a_pending_candidate_can_be_approved(): void
    {
        $base = $this->liveVersion();
        $approver = User::factory()->create();
        $candidate = ResumeEditCandidate::factory()->approved()->create(['base_resume_version_id' => $base->id]);

        $this->expectException(RuntimeException::class);

        $this->service->approve($candidate, $approver->id, $this->service->suggestedNextVersion($base));
    }

    private function liveVersionWithVersionString(string $version): ResumeVersion
    {
        $base = $this->liveVersion();
        $base->update(['version' => $version]);

        return $base->fresh();
    }

    public function test_approve_accepts_a_reviewer_chosen_major_or_minor_version(): void
    {
        $base = $this->liveVersionWithVersionString('2026.1.4');
        $approver = User::factory()->create();
        $candidate = $this->service->resolveOrCreateCandidateForEdit($base, null);

        $this->service->approve($candidate, $approver->id, '2026.2.0');

        $newLive = ResumeVersion::current()->first();
        $this->assertSame('2026.2.0', $newLive->version);
    }

    public function test_approve_rejects_a_version_that_does_not_exceed_the_base_version(): void
    {
        $base = $this->liveVersionWithVersionString('2026.1.4');
        $approver = User::factory()->create();
        $candidate = $this->service->resolveOrCreateCandidateForEdit($base, null);

        try {
            $this->service->approve($candidate, $approver->id, '2026.1.4');
            $this->fail('Expected an InvalidArgumentException to be thrown.');
        } catch (InvalidArgumentException) {
            // expected
        }

        $this->assertSame('pending', $candidate->fresh()->status);
        $this->assertTrue($base->fresh()->is_current);
    }

    public function test_approve_rejects_a_malformed_version_string(): void
    {
        $base = $this->liveVersion();
        $approver = User::factory()->create();
        $candidate = $this->service->resolveOrCreateCandidateForEdit($base, null);

        $this->expectException(InvalidArgumentException::class);

        $this->service->approve($candidate, $approver->id, 'not-a-version');
    }

    public function test_reject_permanently_deletes_the_candidate_and_leaves_live_version_untouched(): void
    {
        $base = $this->liveVersion();
        $candidate = $this->service->resolveOrCreateCandidateForEdit($base, null);

        $this->service->reject($candidate);

        $this->assertDatabaseMissing('resume_edit_candidates', ['id' => $candidate->id]);
        $this->assertTrue($base->fresh()->is_current);
    }

    public function test_edit_after_rejection_branches_a_fresh_candidate_from_the_unchanged_live_version(): void
    {
        $base = $this->liveVersion();
        $candidate = $this->service->resolveOrCreateCandidateForEdit($base, null);
        $this->service->reject($candidate);

        $fresh = $this->service->resolveOrCreateCandidateForEdit($base, null);

        $this->assertSame(1, $fresh->revision_number);
        $this->assertSame('Jason Vertucio', $fresh->snapshot['personal']['name']);
    }

    public function test_has_pending_candidate_for_reflects_pending_state(): void
    {
        $base = $this->liveVersion();

        $this->assertFalse($this->service->hasPendingCandidateFor($base));

        $candidate = $this->service->resolveOrCreateCandidateForEdit($base, null);
        $this->assertTrue($this->service->hasPendingCandidateFor($base));

        $this->service->reject($candidate);
        $this->assertFalse($this->service->hasPendingCandidateFor($base));
    }
}
