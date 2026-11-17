<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\FollowUpDiscardReason;
use App\Enums\FollowUpStatus;
use App\Models\Application;
use App\Models\ApplicationFollowUp;
use App\Models\ApplicationStatusUpdate;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Services\ApplicationFollowUpService;
use App\Services\ApplicationService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Follow-ups are switched on per application, and stop with it: switching
 * them off or adding a status entry discards a waiting draft.
 */
class ApplicationFollowUpStopTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private ApplicationFollowUpService $followUps;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-06-01 09:00:00'));
        config(['resume.follow_ups.cadence_days' => 7, 'resume.follow_ups.max_per_status' => 3]);

        $this->admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');

        $this->followUps = app(ApplicationFollowUpService::class);
    }

    public function test_a_new_application_has_follow_ups_off(): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);

        $application = app(ApplicationService::class)->createApplied('Build things.', 'Acme', 'Engineer');

        $this->assertFalse($application->fresh()->follow_ups_enabled);
    }

    public function test_the_switch_is_turned_on_and_off_through_the_update_endpoint(): void
    {
        $application = Application::factory()->withContact()->create(['status' => ApplicationStatus::Applied]);

        $this->actingAs($this->admin)
            ->putJson(route('admin.employment.applications.update', $application), ['follow_ups_enabled' => true])
            ->assertOk()
            ->assertJsonPath('follow_ups.follow_ups_enabled', true);

        $this->assertTrue($application->fresh()->follow_ups_enabled);

        $this->actingAs($this->admin)
            ->putJson(route('admin.employment.applications.update', $application), ['follow_ups_enabled' => 'not-a-bool'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('follow_ups_enabled');
    }

    public function test_updating_other_details_leaves_the_switch_alone(): void
    {
        $application = Application::factory()->followUpsEnabled()->create();

        $this->actingAs($this->admin)
            ->putJson(route('admin.employment.applications.update', $application), ['location' => 'Remote'])
            ->assertOk();

        $this->assertTrue($application->fresh()->follow_ups_enabled);
    }

    public function test_turning_follow_ups_off_discards_the_pending_draft(): void
    {
        $application = $this->dueApplication(ApplicationStatus::Applied, 10);
        $draft = $this->draft($application);
        $sent = ApplicationFollowUp::factory()->sent(now()->subDays(20))->create(['application_id' => $application->id]);

        $this->actingAs($this->admin)
            ->putJson(route('admin.employment.applications.update', $application), ['follow_ups_enabled' => false])
            ->assertOk()
            ->assertJsonPath('follow_ups.follow_ups_enabled', false)
            ->assertJsonPath('follow_ups.pending_follow_up', null);

        $draft->refresh();
        $this->assertSame(FollowUpStatus::Discarded, $draft->status);
        $this->assertSame(FollowUpDiscardReason::Disabled, $draft->discard_reason);
        $this->assertSame(FollowUpStatus::Sent, $sent->fresh()->status);
        $this->assertFalse($this->followUps->isDue($application));
    }

    public function test_an_interviewed_entry_discards_the_draft_and_the_next_follow_up_is_due_a_week_after_it(): void
    {
        $application = $this->dueApplication(ApplicationStatus::Applied, 10);
        $draft = $this->draft($application);

        $this->actingAs($this->admin)
            ->postJson(route('admin.employment.applications.status-updates.store', $application), [
                'status' => 'interviewed',
                'occurred_at' => now()->toDateTimeString(),
            ])
            ->assertOk();

        $this->assertDiscardedAsSuperseded($draft);

        $this->travel(6)->days();
        $this->assertFalse($this->followUps->isDue($application));

        $this->travel(1)->days();
        $this->assertTrue($this->followUps->isDue($application));
    }

    public function test_an_interviewing_entry_discards_the_draft_and_nothing_is_due_while_interviewing(): void
    {
        $application = $this->dueApplication(ApplicationStatus::Applied, 10);
        $draft = $this->draft($application);

        app(ApplicationService::class)->addStatusUpdate($application, ApplicationStatus::Interviewing);

        $this->assertDiscardedAsSuperseded($draft);

        $this->travel(30)->days();
        $this->assertFalse($this->followUps->isDue($application));
    }

    public function test_a_rejected_entry_discards_the_draft_and_nothing_is_ever_due_again(): void
    {
        $application = $this->dueApplication(ApplicationStatus::Applied, 10);
        $draft = $this->draft($application);

        app(ApplicationService::class)->addStatusUpdate($application, ApplicationStatus::Rejected);

        $this->assertDiscardedAsSuperseded($draft);

        $this->travel(60)->days();
        $this->assertFalse($this->followUps->isDue($application));
    }

    public function test_a_deleted_application_is_never_due(): void
    {
        $application = $this->dueApplication(ApplicationStatus::Applied, 10);

        app(ApplicationService::class)->delete($application);

        $this->travel(30)->days();
        $this->assertFalse($this->followUps->isDue($application));
        $this->assertFalse($this->followUps->dueApplications()->contains('id', $application->id));
    }

    public function test_editing_or_deleting_a_status_entry_does_not_discard_the_draft(): void
    {
        $application = $this->dueApplication(ApplicationStatus::Applied, 10);
        $extra = ApplicationStatusUpdate::factory()->create([
            'application_id' => $application->id,
            'status' => ApplicationStatus::Applied,
            'occurred_at' => now()->subDays(9),
        ]);
        $draft = $this->draft($application);
        $entry = $application->statusUpdates()->first();

        $this->actingAs($this->admin)
            ->putJson(route('admin.employment.applications.status-updates.update', [$application, $entry]), [
                'notes' => 'Corrected.',
                'occurred_at' => now()->subDays(11)->toDateTimeString(),
            ])
            ->assertOk();

        $this->actingAs($this->admin)
            ->deleteJson(route('admin.employment.applications.status-updates.destroy', [$application, $extra]))
            ->assertOk();

        $this->assertSame(FollowUpStatus::Pending, $draft->fresh()->status);
    }

    private function dueApplication(ApplicationStatus $status, int $entryDaysAgo): Application
    {
        $application = Application::factory()->followUpsEnabled()->withContact()->create(['status' => $status]);

        ApplicationStatusUpdate::factory()->create([
            'application_id' => $application->id,
            'status' => $status,
            'occurred_at' => now()->subDays($entryDaysAgo),
            'created_at' => now()->subDays($entryDaysAgo),
        ]);

        return $application;
    }

    private function draft(Application $application): ApplicationFollowUp
    {
        return ApplicationFollowUp::factory()->pending()->create(['application_id' => $application->id]);
    }

    private function assertDiscardedAsSuperseded(ApplicationFollowUp $draft): void
    {
        $draft->refresh();

        $this->assertSame(FollowUpStatus::Discarded, $draft->status);
        $this->assertSame(FollowUpDiscardReason::Superseded, $draft->discard_reason);
        $this->assertNotNull($draft->discarded_at);
    }
}
