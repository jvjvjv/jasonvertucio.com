<?php

namespace Tests\Feature;

use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use App\Models\User;
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
}
