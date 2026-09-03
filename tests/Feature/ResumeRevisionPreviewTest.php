<?php

namespace Tests\Feature;

use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The review page is the one place a malformed draft is meant to be looked at,
 * so it has to render one rather than 500 — otherwise the only way to see a bad
 * revision is the only way you can't.
 */
class ResumeRevisionPreviewTest extends TestCase
{
    use DatabaseTransactions;

    private function reviewer(): User
    {
        Permission::firstOrCreate(['name' => 'edit-resume']);
        Permission::firstOrCreate(['name' => 'read-resume']);

        $reviewer = User::factory()->create();
        $reviewer->givePermissionTo('edit-resume');
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

    public function test_it_renders_a_well_formed_candidate_revision(): void
    {
        $reviewer = $this->reviewer();
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create([
            'base_resume_version_id' => $base->id,
            'revision_number' => 1,
        ]);

        $response = $this->actingAs($reviewer)->get("/resume?revision={$candidate->id}");

        $response->assertOk();
        $response->assertSee('Revision #1');
    }

    /**
     * The exact snapshot that 500'd in production: `experience` written as one
     * job object rather than the list of jobs, so the view's `@foreach` iterated
     * that job's own fields and `$job['jobTitle']` did not exist.
     */
    public function test_it_renders_a_candidate_whose_experience_section_is_a_single_object(): void
    {
        $reviewer = $this->reviewer();
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create([
            'base_resume_version_id' => $base->id,
            'revision_number' => 1,
            'snapshot' => [
                'personal' => [
                    'name' => 'Jason Vertucio',
                    'title' => 'Lead Front-End Engineer',
                    'email' => 'jasonvertucio@pm.me',
                    'summary' => 'Lead Front-End Engineer.',
                ],
                'skills' => ['top' => [], 'other' => []],
                'experience' => [
                    'jobTitle' => 'Angular/React/Vue Developer',
                    'company' => 'Subaru of America',
                    'dates' => ['2025-06-02', '2026-06-02'],
                    'bullets' => ['Did the thing.'],
                ],
                'education' => [['institution' => 'Drexel University']],
                'projects' => [['projectName' => 'jasonvertucio.com']],
            ],
        ]);

        $response = $this->actingAs($reviewer)->get("/resume?revision={$candidate->id}");

        $response->assertOk();
    }

    public function test_it_renders_a_candidate_with_sections_missing_entirely(): void
    {
        $reviewer = $this->reviewer();
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create([
            'base_resume_version_id' => $base->id,
            'revision_number' => 1,
            'snapshot' => ['personal' => ['name' => 'Jason Vertucio']],
        ]);

        $response = $this->actingAs($reviewer)->get("/resume?revision={$candidate->id}");

        $response->assertOk();
    }

    public function test_it_renders_entries_missing_their_required_fields(): void
    {
        $reviewer = $this->reviewer();
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create([
            'base_resume_version_id' => $base->id,
            'revision_number' => 1,
            'snapshot' => [
                'personal' => ['name' => 'Jason Vertucio', 'title' => 'Engineer'],
                'skills' => ['top' => [], 'other' => []],
                'experience' => [['company' => 'Acme']],
                'education' => [['degree' => 'Computer Science']],
                'projects' => [['description' => 'No name.']],
            ],
        ]);

        $response = $this->actingAs($reviewer)->get("/resume?revision={$candidate->id}");

        $response->assertOk();
    }

    /**
     * Approve/reject redirect back here, so this page is where their outcome has
     * to be readable — otherwise a refused approval looks like nothing happened.
     */
    public function test_it_shows_the_outcome_flashed_by_an_approve_or_reject(): void
    {
        $reviewer = $this->reviewer();
        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create([
            'base_resume_version_id' => $base->id,
            'revision_number' => 1,
        ]);

        $response = $this->actingAs($reviewer)
            ->withSession(['error' => 'This revision cannot be published: Job title is required.'])
            ->get("/resume?revision={$candidate->id}");

        $response->assertOk();
        $response->assertSee('This revision cannot be published: Job title is required.');
    }

    public function test_a_viewer_without_edit_resume_cannot_open_a_revision(): void
    {
        Permission::firstOrCreate(['name' => 'read-resume']);
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('read-resume');

        $base = $this->liveVersion();
        $candidate = ResumeEditCandidate::factory()->create([
            'base_resume_version_id' => $base->id,
            'revision_number' => 1,
        ]);

        $this->actingAs($viewer)->get("/resume?revision={$candidate->id}")->assertForbidden();
    }
}
