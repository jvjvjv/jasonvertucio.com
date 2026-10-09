<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationStatusUpdateTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');
    }

    public function test_can_log_applied_status_update(): void
    {
        $application = Application::factory()->withTargetedResume()->withConversation()->create();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.status-updates.store', $application), [
                'status' => 'applied',
                'notes' => null,
                'occurred_at' => null,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('status', 'applied');

        $this->assertDatabaseHas('application_status_updates', [
            'application_id' => $application->id,
            'status' => 'applied',
        ]);

        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
    }

    public function test_applied_status_creates_history_record_with_today_date_by_default(): void
    {
        $application = Application::factory()->withTargetedResume()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.status-updates.store', $application), [
                'status' => 'applied',
            ])
            ->assertOk();

        $update = $application->statusUpdates()->first();
        $this->assertNotNull($update);
        $this->assertSame(ApplicationStatus::Applied, $update->status);
        $this->assertTrue(Carbon::today()->isSameDay($update->occurred_at));
    }

    public function test_interviewing_status_with_scheduled_date(): void
    {
        $application = Application::factory()->applied()->create();

        $scheduledDate = '2026-06-12';

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.status-updates.store', $application), [
                'status' => 'interviewing',
                'occurred_at' => $scheduledDate,
                'notes' => 'Round 1 with hiring manager',
            ]);

        $response->assertOk();

        $update = ApplicationStatusUpdate::query()
            ->where('application_id', $application->id)
            ->where('status', 'interviewing')
            ->first();

        $this->assertNotNull($update);
        $this->assertSame('Round 1 with hiring manager', $update->notes);
        $this->assertSame($scheduledDate, $update->occurred_at->toDateString());

        $this->assertSame(ApplicationStatus::Interviewing, $application->fresh()->status);
    }

    public function test_multiple_interview_rounds_can_be_logged(): void
    {
        $application = Application::factory()->applied(Carbon::parse('2026-06-01'))->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.status-updates.store', $application), [
                'status' => 'interviewing',
                'occurred_at' => '2026-06-12',
                'notes' => 'Round 1',
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.status-updates.store', $application), [
                'status' => 'interviewed',
                'occurred_at' => '2026-06-12',
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.status-updates.store', $application), [
                'status' => 'interviewing',
                'occurred_at' => '2026-06-19',
                'notes' => 'Round 2',
            ])
            ->assertOk();

        $this->assertSame(4, $application->statusUpdates()->count());
        $this->assertSame(ApplicationStatus::Interviewing, $application->fresh()->status);
    }

    public function test_rejected_after_applied(): void
    {
        $application = Application::factory()->applied()->create();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.status-updates.store', $application), [
                'status' => 'rejected',
                'notes' => 'Position filled internally.',
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'rejected');
        $response->assertJsonPath('allowed_next_statuses', []);

        $this->assertSame(ApplicationStatus::Rejected, $application->fresh()->status);
    }

    public function test_cannot_add_status_update_to_terminal_application(): void
    {
        $application = Application::factory()->create(['status' => ApplicationStatus::Rejected]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.status-updates.store', $application), [
                'status' => 'applied',
            ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
        $this->assertSame(0, $application->statusUpdates()->count());
    }

    public function test_can_add_status_update_without_a_targeted_resume(): void
    {
        $resumeVersion = ResumeVersion::factory()->create();
        $application = Application::factory()->withConversation()->create([
            'resume_version_id' => $resumeVersion->id,
            'company_name' => 'Example Company',
            'position' => 'Senior Engineer',
            'job_description' => 'Role details',
        ]);
        $targetedResumeCount = TargetedResume::query()->count();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.status-updates.store', $application), [
                'status' => 'applied',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('status', 'applied');

        $application->refresh();

        $this->assertNull($application->targeted_resume_id);
        $this->assertSame($targetedResumeCount, TargetedResume::query()->count());
        $this->assertSame($resumeVersion->id, $application->resume_version_id);
        $this->assertSame(ApplicationStatus::Applied, $application->status);
    }

    public function test_marking_applied_records_the_chosen_resume_version(): void
    {
        $original = ResumeVersion::factory()->create();
        $chosen = ResumeVersion::factory()->create();
        $application = Application::factory()->passed()->create(['resume_version_id' => $original->id]);

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.apply', $application), [
                'resume_version_id' => $chosen->id,
                'occurred_at' => '2026-07-04',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('status', 'applied');
        $response->assertJsonCount(1, 'status_updates');
        $this->assertContains('interviewing', $response->json('allowed_next_statuses'));

        $application->refresh();

        $this->assertSame($chosen->id, $application->resume_version_id);
        $this->assertSame(ApplicationStatus::Applied, $application->status);
        $this->assertSame('2026-07-04', $application->statusUpdates()->first()->occurred_at->toDateString());
    }

    public function test_marking_applied_with_a_targeted_resume_needs_no_input_and_keeps_the_resume(): void
    {
        $application = Application::factory()->withTargetedResume()->create();
        $resumeVersionId = $application->resume_version_id;
        $ignored = ResumeVersion::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.apply', $application))
            ->assertOk()
            ->assertJsonPath('status', 'applied');

        $application->refresh();

        $this->assertNotNull($application->targeted_resume_id);
        $this->assertSame($resumeVersionId, $application->resume_version_id);
        $this->assertNotSame($ignored->id, $application->resume_version_id);
    }

    public function test_marking_a_terminal_application_applied_is_refused(): void
    {
        $application = Application::factory()->create(['status' => ApplicationStatus::Hired]);

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.apply', $application))
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_invalid_status_value_is_rejected(): void
    {
        $application = Application::factory()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.status-updates.store', $application), [
                'status' => 'dancing',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_non_pipeline_status_values_are_rejected(): void
    {
        $application = Application::factory()->create();

        foreach (['draft', 'passed'] as $status) {
            $this->actingAs($this->admin)
                ->postJson(route('admin.resume.applications.status-updates.store', $application), [
                    'status' => $status,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['status']);
        }

        $this->assertSame(0, $application->statusUpdates()->count());
    }

    public function test_index_status_filter_returns_only_matching_statuses(): void
    {
        $rejected = Application::factory()->create(['status' => ApplicationStatus::Rejected]);
        $applied = Application::factory()->applied()->create();

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.applications.index', ['status' => ['rejected']]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('resume/applications/Index', false)
            ->where('applications', fn ($applications) => collect($applications)->pluck('id')->contains($rejected->id)
                && ! collect($applications)->pluck('id')->contains($applied->id)
            )
        );
    }

    public function test_allowed_next_statuses_returned_correctly_for_applied(): void
    {
        $application = Application::factory()->applied()->create();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.status-updates.store', $application), [
                'status' => 'interviewing',
            ]);

        $response->assertOk();
        $allowedNext = $response->json('allowed_next_statuses');
        $this->assertContains('interviewed', $allowedNext);
        $this->assertContains('rejected', $allowedNext);
        $this->assertNotContains('applied', $allowedNext);
    }

    public function test_response_includes_full_status_update_history(): void
    {
        $application = Application::factory()->applied(now()->subDays(5))->create();

        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.status-updates.store', $application), [
                'status' => 'interviewing',
            ]);

        $response->assertOk();
        $statusUpdates = $response->json('status_updates');
        $this->assertCount(2, $statusUpdates);
        $this->assertSame('applied', $statusUpdates[0]['status']);
        $this->assertSame('interviewing', $statusUpdates[1]['status']);
    }

    public function test_can_edit_status_update_date_and_notes(): void
    {
        $application = Application::factory()->create(['status' => ApplicationStatus::Applied]);

        $statusUpdate = ApplicationStatusUpdate::create([
            'application_id' => $application->id,
            'status' => 'applied',
            'notes' => 'Initial note',
            'occurred_at' => '2026-06-10 00:00:00',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/resume/applications/{$application->id}/status-updates/{$statusUpdate->id}", [
                'notes' => 'Updated note',
                'occurred_at' => '2026-06-12',
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('status', 'applied');
        $response->assertJsonPath('status_updates.0.notes', 'Updated note');

        $statusUpdate->refresh();

        $this->assertSame('Updated note', $statusUpdate->notes);
        $this->assertSame('2026-06-12', $statusUpdate->occurred_at?->toDateString());
    }

    public function test_editing_a_status_update_requires_a_date(): void
    {
        $application = Application::factory()->applied()->create();
        $statusUpdate = $application->statusUpdates()->first();

        $this->actingAs($this->admin)
            ->putJson(route('admin.resume.applications.status-updates.update', [$application, $statusUpdate]), [
                'notes' => 'No date',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['occurred_at']);
    }

    public function test_can_delete_status_update_and_recalculate_latest_status(): void
    {
        $application = Application::factory()->create(['status' => ApplicationStatus::Interviewing]);

        ApplicationStatusUpdate::create([
            'application_id' => $application->id,
            'status' => 'applied',
            'occurred_at' => '2026-06-10 00:00:00',
        ]);

        $latestStatusUpdate = ApplicationStatusUpdate::create([
            'application_id' => $application->id,
            'status' => 'interviewing',
            'occurred_at' => '2026-06-12 00:00:00',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/admin/resume/applications/{$application->id}/status-updates/{$latestStatusUpdate->id}");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('status', 'applied');

        $this->assertDatabaseMissing('application_status_updates', [
            'id' => $latestStatusUpdate->id,
        ]);

        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
    }

    public function test_deleting_the_only_status_update_returns_the_application_to_draft(): void
    {
        $application = Application::factory()->applied()->withTargetedResume()->create();
        $statusUpdate = $application->statusUpdates()->first();

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.resume.applications.status-updates.destroy', [$application, $statusUpdate]))
            ->assertOk()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('status_updates', [])
            ->assertJsonPath('allowed_next_statuses', ['applied']);

        $this->assertSame(ApplicationStatus::Draft, $application->fresh()->status);
    }

    public function test_a_status_update_cannot_be_edited_or_deleted_through_another_application(): void
    {
        $owner = Application::factory()->applied()->create();
        $other = Application::factory()->applied()->create();
        $statusUpdate = $owner->statusUpdates()->first();
        $originalDate = $statusUpdate->occurred_at->toDateString();
        $originalNotes = $statusUpdate->notes;

        $this->actingAs($this->admin)
            ->putJson(route('admin.resume.applications.status-updates.update', [$other, $statusUpdate]), [
                'notes' => 'Hijacked',
                'occurred_at' => '2020-01-01',
            ])
            ->assertNotFound()
            ->assertJsonStructure(['message']);

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.resume.applications.status-updates.destroy', [$other, $statusUpdate]))
            ->assertNotFound();

        $statusUpdate->refresh();

        $this->assertSame($originalNotes, $statusUpdate->notes);
        $this->assertSame($originalDate, $statusUpdate->occurred_at->toDateString());
        $this->assertSame(ApplicationStatus::Applied, $owner->fresh()->status);
        $this->assertSame(ApplicationStatus::Applied, $other->fresh()->status);
    }

    public function test_passing_is_refused_once_an_application_is_in_the_pipeline(): void
    {
        $application = Application::factory()->applied()->create();

        $this->actingAs($this->admin)
            ->postJson(route('admin.resume.applications.pass', $application))
            ->assertStatus(409)
            ->assertJsonStructure(['message']);

        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
    }
}
