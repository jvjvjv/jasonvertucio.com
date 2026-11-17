<?php

namespace Tests\Feature;

use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Services\DatabaseResumeDataService;
use App\Services\ResumeEditCandidateService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResumeEditCandidateReviewTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        Permission::firstOrCreate(['name' => 'edit-resume']);

        $admin = User::factory()->create();
        $admin->givePermissionTo('edit-resume');

        return $admin;
    }

    /**
     * Someone who may open the résumé page and review drafts on it.
     */
    private function reviewer(): User
    {
        Permission::firstOrCreate(['name' => 'read-resume']);
        $reviewer = $this->admin();
        $reviewer->givePermissionTo('read-resume');

        return $reviewer;
    }

    private function liveVersion(): ResumeVersion
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);
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

    public function test_manual_save_is_refused_while_a_candidate_is_pending(): void
    {
        $admin = $this->admin();
        $base = $this->liveVersion();
        ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => 1]);

        $response = $this->actingAs($admin)->postJson('/api/admin/resume/editor', [
            'version' => $base->version,
            'data' => [
                'personal' => ['name' => 'Someone Else', 'title' => 'x', 'email' => 'a@b.com'],
                'skills' => ['top' => [], 'other' => []],
                'experience' => [['jobTitle' => 'x', 'company' => 'y']],
                'education' => [['institution' => 'x']],
                'projects' => [['projectName' => 'x']],
            ],
        ]);

        $response->assertStatus(409);
        $this->assertSame('Jason Vertucio', $base->fresh()->personalInfo->name);
    }

    public function test_manual_save_succeeds_once_the_pending_candidate_is_resolved(): void
    {
        $admin = $this->admin();
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => 1]);
        $candidate->update(['status' => 'approved']);

        $response = $this->actingAs($admin)->postJson('/api/admin/resume/editor', [
            'version' => $base->version,
            'data' => [
                'personal' => ['name' => 'Someone Else', 'title' => 'x', 'email' => 'a@b.com'],
                'skills' => ['top' => [], 'other' => []],
                'experience' => [['jobTitle' => 'x', 'company' => 'y']],
                'education' => [['institution' => 'x']],
                'projects' => [['projectName' => 'x']],
            ],
        ]);

        $response->assertStatus(200);
    }

    public function test_approve_endpoint_materializes_a_new_live_version(): void
    {
        $admin = $this->admin();
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create([
            'base_resume_version_id' => $base->id,
            'revision_number' => 1,
            'snapshot' => [
                'personal' => ['name' => 'Approved Name', 'title' => 'Engineer', 'email' => 'jason@example.com'],
                'skills' => ['top' => [], 'other' => []],
                'experience' => [['jobTitle' => 'Engineer', 'company' => 'Acme', 'bullets' => []]],
                'education' => [['institution' => 'State University']],
                'projects' => [['projectName' => 'Side Project', 'bullets' => []]],
            ],
        ]);

        $response = $this->actingAs($admin)->post("/admin/resume/candidates/{$candidate->id}/approve", [
            'version' => app(ResumeEditCandidateService::class)->suggestedNextVersion($base),
        ]);

        $response->assertRedirect();
        $this->assertSame('approved', $candidate->fresh()->status);
        $this->assertFalse($base->fresh()->is_current);
        $this->assertSame('Approved Name', ResumeVersion::current()->first()->personalInfo->name);
    }

    public function test_approve_endpoint_rejects_a_version_that_does_not_exceed_the_base(): void
    {
        $admin = $this->admin();
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => 1]);

        $response = $this->actingAs($admin)->post("/admin/resume/candidates/{$candidate->id}/approve", [
            'version' => $base->version,
        ]);

        $response->assertRedirect();
        $this->assertSame('pending', $candidate->fresh()->status);
        $this->assertTrue($base->fresh()->is_current);
    }

    /**
     * A draft written before the section shapes were validated can still be
     * sitting in the table. Approving it must fail with something the reviewer
     * can read, not a 500 or a silent no-op, so the only way out of a bad draft
     * isn't editing the database by hand.
     */
    public function test_approving_a_malformed_candidate_reports_the_problem(): void
    {
        $admin = $this->admin();
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create([
            'base_resume_version_id' => $base->id,
            'revision_number' => 1,
            'snapshot' => [
                'personal' => ['name' => 'Jason Vertucio', 'title' => 'Engineer', 'email' => 'jason@example.com'],
                'skills' => ['top' => [], 'other' => []],
                'experience' => ['jobTitle' => 'Solo Object', 'company' => 'Acme'],
                'education' => [['institution' => 'State University']],
                'projects' => [['projectName' => 'Side Project']],
            ],
        ]);

        $response = $this->actingAs($admin)->post("/admin/resume/candidates/{$candidate->id}/approve", [
            'version' => app(ResumeEditCandidateService::class)->suggestedNextVersion($base),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame('pending', $candidate->fresh()->status);
        $this->assertTrue($base->fresh()->is_current);
        $this->assertSame('Jason Vertucio', $base->fresh()->personalInfo->name);
    }

    public function test_reject_endpoint_permanently_deletes_the_candidate(): void
    {
        $admin = $this->admin();
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => 1]);

        $response = $this->actingAs($admin)->post("/admin/resume/candidates/{$candidate->id}/reject");

        $response->assertRedirect();
        $this->assertDatabaseMissing('resume_edit_candidates', ['id' => $candidate->id]);
        $this->assertTrue($base->fresh()->is_current);
    }

    public function test_non_admin_cannot_approve_or_reject(): void
    {
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $user = User::factory()->create();
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => 1]);

        $this->actingAs($user)->post("/admin/resume/candidates/{$candidate->id}/approve")->assertForbidden();
        $this->actingAs($user)->post("/admin/resume/candidates/{$candidate->id}/reject")->assertForbidden();
    }

    /**
     * The live version from liveVersion(), as a draft snapshot, with one
     * bullet reworded and one skill added.
     *
     * @return array<string, mixed>
     */
    private function editedSnapshot(ResumeVersion $base): array
    {
        $base->personalInfo->update(['summary' => 'Builds things.']);
        $base->experiences()->first()->bullets()->create(['content' => 'Led a team of 4 engineers', 'sort_order' => 0]);
        $base->skillCategories()->create(['group' => 'top', 'title' => 'Languages', 'sort_order' => 0])
            ->skills()->create(['name' => 'PHP', 'sort_order' => 0]);

        $snapshot = app(DatabaseResumeDataService::class)->getAllEditableData($base->fresh());
        $snapshot['experience'][0]['bullets'][0] = 'Led a team of 6 engineers';
        $snapshot['skills']['top'][0]['list'][] = 'Rust';

        return $snapshot;
    }

    public function test_a_revision_is_compared_with_the_version_it_branched_from_by_default(): void
    {
        ResumeVersion::query()->update(['is_current' => false]);
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'snapshot' => $this->editedSnapshot($base)]);

        $response = $this->actingAs($this->reviewer())->get("/resume?revision={$candidate->id}");

        $response->assertOk()
            ->assertViewHas('baseline', fn ($baseline): bool => $baseline->mode === 'base')
            ->assertViewHas('diff', fn ($diff): bool => $diff->count() === 2)
            ->assertSee('2 changes')
            ->assertSee("Compared with version {$base->version}, the one it branched from.", false)
            ->assertSee('<del class="rd-del">4</del>', false)
            ->assertSee('<ins class="rd-ins">6</ins>', false)
            ->assertSee('x-data="resumeDiffToggle"', false)
            ->assertDontSee('name="compare"', false);
    }

    public function test_a_stale_revision_can_be_compared_with_the_live_resume(): void
    {
        ResumeVersion::query()->update(['is_current' => false]);
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'snapshot' => $this->editedSnapshot($base)]);
        $base->update(['is_current' => false]);
        $live = $this->liveVersion();

        $response = $this->actingAs($this->reviewer())->get("/resume?revision={$candidate->id}&compare=live");

        $response->assertOk()
            ->assertViewHas('baseline', fn ($baseline): bool => $baseline->mode === 'live')
            ->assertSee("Compared with the live resume, version {$live->version}.", false)
            ->assertSee('<option value="live" selected>', false)
            ->assertSee('<option value="base"', false);
    }

    public function test_a_revision_can_be_compared_with_the_previous_revision(): void
    {
        ResumeVersion::query()->update(['is_current' => false]);
        $base = $this->liveVersion();
        $snapshot = $this->editedSnapshot($base);
        ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => 1, 'snapshot' => $snapshot]);
        $snapshot['projects'][0]['description'] = 'Now with a description';
        $second = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => 2, 'snapshot' => $snapshot]);

        $response = $this->actingAs($this->reviewer())->get("/resume?revision={$second->id}&compare=previous");

        $response->assertOk()
            ->assertViewHas('diff', fn ($diff): bool => $diff->count() === 1)
            ->assertSee('1 change')
            ->assertSee('Compared with revision #1.', false);
    }

    public function test_an_unavailable_baseline_falls_back_with_a_notice(): void
    {
        ResumeVersion::query()->update(['is_current' => false]);
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'snapshot' => $this->editedSnapshot($base)]);

        $this->actingAs($this->reviewer())->get("/resume?revision={$candidate->id}&compare[]=live")
            ->assertOk()
            ->assertViewHas('baseline', fn ($baseline): bool => $baseline->mode === 'base' && ! $baseline->fellBack);

        foreach (['previous', 'sideways'] as $requested) {
            $this->actingAs($this->reviewer())->get("/resume?revision={$candidate->id}&compare={$requested}")
                ->assertOk()
                ->assertViewHas('baseline', fn ($baseline): bool => $baseline->mode === 'base' && $baseline->fellBack)
                ->assertSee("That comparison isn't available for this revision", false);
        }
    }

    public function test_an_unauthorized_viewer_gets_no_diff(): void
    {
        Permission::firstOrCreate(['name' => 'read-resume']);
        $reader = User::factory()->create();
        $reader->givePermissionTo('read-resume');
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id]);

        $this->actingAs($reader)->get("/resume?revision={$candidate->id}&compare=live")->assertForbidden();
        $this->get("/resume?revision={$candidate->id}")->assertForbidden();
    }

    public function test_json_and_plain_text_revisions_are_unchanged(): void
    {
        ResumeVersion::query()->update(['is_current' => false]);
        $base = $this->liveVersion();
        $snapshot = $this->editedSnapshot($base);
        $candidate = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'snapshot' => $snapshot]);
        $admin = $this->reviewer();

        $this->actingAs($admin)->getJson("/resume?revision={$candidate->id}&compare=base")
            ->assertOk()
            ->assertExactJson($snapshot);

        $plain = $this->actingAs($admin)->get("/resume?revision={$candidate->id}", ['Accept' => 'text/plain']);
        $plain->assertOk();
        $this->assertStringNotContainsString('rd-', $plain->getContent());
        $this->assertStringContainsString('Led a team of 6 engineers', $plain->getContent());
    }

    public function test_the_resume_without_a_revision_has_no_panel_or_toggle(): void
    {
        ResumeVersion::query()->update(['is_current' => false]);
        $this->liveVersion();

        $this->actingAs($this->reviewer())->get('/resume')
            ->assertOk()
            ->assertViewHas('diff', null)
            ->assertDontSee('rd-panel', false)
            ->assertDontSee('resumeDiffToggle', false);
    }
}
