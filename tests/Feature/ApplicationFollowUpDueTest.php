<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\FollowUpStatus;
use App\Models\Application;
use App\Models\ApplicationFollowUp;
use App\Models\ApplicationStatusUpdate;
use App\Services\ApplicationFollowUpService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * When an application is due for a follow-up. Status entries and sends are
 * dated explicitly: the cadence runs from an entry's `occurred_at` and the
 * last send, the per-stage maximum from the entry's `created_at`.
 */
class ApplicationFollowUpDueTest extends TestCase
{
    use DatabaseTransactions;

    private ApplicationFollowUpService $followUps;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-06-01 09:00:00'));
        config(['resume.follow_ups.cadence_days' => 7, 'resume.follow_ups.max_per_status' => 3]);

        $this->followUps = app(ApplicationFollowUpService::class);
    }

    public function test_due_a_week_after_applying(): void
    {
        $application = $this->application();
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(7));

        $this->assertDue($application);
    }

    public function test_not_due_after_six_days(): void
    {
        $application = $this->application();
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(6));

        $this->assertNotDue($application);
    }

    public function test_due_weekly_after_a_sent_follow_up(): void
    {
        $application = $this->application();
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(14));
        $this->sent($application, now()->subDays(7));

        $this->assertDue($application);
    }

    public function test_not_due_six_days_after_a_sent_follow_up(): void
    {
        $application = $this->application();
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(14));
        $this->sent($application, now()->subDays(6));

        $this->assertNotDue($application);
    }

    public function test_paused_while_interviewing(): void
    {
        $application = $this->application(ApplicationStatus::Interviewing);
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(20));
        $this->entry($application, ApplicationStatus::Interviewing, now()->subDays(10));

        $this->assertNotDue($application);
    }

    public function test_resumes_a_week_after_the_interview(): void
    {
        $application = $this->application(ApplicationStatus::Interviewed);
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(30));
        $this->entry($application, ApplicationStatus::Interviewing, now()->subDays(20));
        $this->entry($application, ApplicationStatus::Interviewed, now()->subDays(7));

        $this->assertDue($application);
    }

    public function test_not_due_once_the_maximum_has_been_sent_since_the_entry(): void
    {
        $application = $this->application();
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(30));
        $this->sent($application, now()->subDays(21));
        $this->sent($application, now()->subDays(14));
        $this->sent($application, now()->subDays(7));

        $this->assertNotDue($application);
    }

    public function test_the_maximum_resets_on_a_new_status_entry(): void
    {
        $application = $this->application(ApplicationStatus::Interviewed);
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(40));
        $this->sent($application, now()->subDays(33));
        $this->sent($application, now()->subDays(26));
        $this->sent($application, now()->subDays(19));
        $this->entry($application, ApplicationStatus::Interviewed, now()->subDays(7));

        $this->assertDue($application);
        $this->assertSame(0, $this->followUps->sentSinceStatusChange($application));
    }

    public function test_a_backdated_entry_cannot_uncount_follow_ups_already_sent(): void
    {
        $application = $this->application(ApplicationStatus::Interviewed);
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(40));
        $this->sent($application, now()->subDays(30));
        $this->sent($application, now()->subDays(20));
        $this->sent($application, now()->subDays(10));
        // Recorded before the sends, though it happened later.
        $this->entry($application, ApplicationStatus::Interviewed, now()->subDays(8), now()->subDays(35));

        $this->assertSame(3, $this->followUps->sentSinceStatusChange($application));
        $this->assertNotDue($application);
    }

    public function test_discarded_and_failed_follow_ups_do_not_count_toward_the_maximum(): void
    {
        $application = $this->application();
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(30));

        foreach ([21, 14, 7] as $days) {
            ApplicationFollowUp::factory()->discarded()->create(['application_id' => $application->id, 'created_at' => now()->subDays($days)]);
        }

        ApplicationFollowUp::factory()->failed()->create(['application_id' => $application->id]);

        $this->assertDue($application);
    }

    public function test_the_maximum_is_read_from_config(): void
    {
        config(['resume.follow_ups.max_per_status' => 1]);

        $application = $this->application();
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(30));
        $this->sent($application, now()->subDays(7));

        $this->assertNotDue($application);

        config(['resume.follow_ups.max_per_status' => 2]);

        $this->assertDue($application);
    }

    public function test_the_cadence_is_read_from_config(): void
    {
        config(['resume.follow_ups.cadence_days' => 10]);

        $application = $this->application();
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(9));

        $this->assertNotDue($application);
    }

    public function test_a_ghosted_application_is_not_due(): void
    {
        $application = $this->application(ApplicationStatus::Ghosted);
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(60));
        $this->entry($application, ApplicationStatus::Ghosted, now()->subDays(30));

        $this->assertNotDue($application);
    }

    /**
     * @return array<string, array{0: ApplicationStatus}>
     */
    public static function excludedStatusProvider(): array
    {
        return [
            'draft' => [ApplicationStatus::Draft],
            'passed' => [ApplicationStatus::Passed],
            'interviewing' => [ApplicationStatus::Interviewing],
            'ghosted' => [ApplicationStatus::Ghosted],
            'accepted' => [ApplicationStatus::Accepted],
            'hired' => [ApplicationStatus::Hired],
            'rejected' => [ApplicationStatus::Rejected],
            'withdrawn' => [ApplicationStatus::Withdrawn],
        ];
    }

    #[DataProvider('excludedStatusProvider')]
    public function test_not_due_outside_a_follow_up_status(ApplicationStatus $status): void
    {
        $application = $this->application($status);
        $this->entry($application, $status->isPipeline() ? $status : ApplicationStatus::Applied, now()->subDays(60));

        $this->assertNotDue($application);
    }

    public function test_not_due_without_contacts(): void
    {
        $application = Application::factory()->followUpsEnabled()->create(['status' => ApplicationStatus::Applied]);
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(10));

        $this->assertNotDue($application);
    }

    public function test_not_due_with_a_draft_already_waiting(): void
    {
        $application = $this->application();
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(10));
        ApplicationFollowUp::factory()->pending()->create(['application_id' => $application->id]);

        $this->assertNotDue($application);
    }

    public function test_not_due_while_a_follow_up_is_being_sent(): void
    {
        $application = $this->application();
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(10));
        ApplicationFollowUp::factory()->create(['application_id' => $application->id, 'status' => FollowUpStatus::Sending]);

        $this->assertNotDue($application);
    }

    public function test_a_deleted_application_is_not_due(): void
    {
        $application = $this->application();
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(10));

        $application->delete();

        $this->assertNotDue($application);
    }

    public function test_not_due_with_the_switch_off(): void
    {
        $application = Application::factory()->withContact()->create(['status' => ApplicationStatus::Applied]);
        $this->entry($application, ApplicationStatus::Applied, now()->subDays(10));

        $this->assertNotDue($application);
    }

    private function application(ApplicationStatus $status = ApplicationStatus::Applied): Application
    {
        return Application::factory()->followUpsEnabled()->withContact()->create(['status' => $status]);
    }

    private function entry(Application $application, ApplicationStatus $status, CarbonInterface $occurredAt, ?CarbonInterface $recordedAt = null): void
    {
        ApplicationStatusUpdate::factory()->create([
            'application_id' => $application->id,
            'status' => $status,
            'occurred_at' => $occurredAt,
            'created_at' => $recordedAt ?? $occurredAt,
        ]);
    }

    private function sent(Application $application, CarbonInterface $sentAt): void
    {
        ApplicationFollowUp::factory()->sent($sentAt)->create(['application_id' => $application->id]);
    }

    private function assertDue(Application $application): void
    {
        $this->assertTrue($this->followUps->dueApplications()->contains('id', $application->id), 'Expected the application to be due.');
        $this->assertTrue($this->followUps->isDue($application));
    }

    private function assertNotDue(Application $application): void
    {
        $this->assertFalse($this->followUps->dueApplications()->contains('id', $application->id), 'Expected the application not to be due.');
        $this->assertFalse($this->followUps->isDue($application));
    }
}
