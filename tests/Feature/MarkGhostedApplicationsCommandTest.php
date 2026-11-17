<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Services\ApplicationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MarkGhostedApplicationsCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-06-15 12:00:00'));
        config()->set('resume.ghosted_after_days', 30);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    public function test_it_marks_silent_applied_and_interviewed_applications_with_a_backdated_entry(): void
    {
        $applied = Application::factory()->applied(now()->subDays(31))->create();
        $interviewed = $this->withEntry(Application::factory()->applied(now()->subDays(90))->create(), ApplicationStatus::Interviewed, now()->subDays(45));
        $recent = Application::factory()->applied(now()->subDays(29))->create();

        $this->artisan('applications:mark-ghosted')->assertSuccessful();

        $this->assertSame(ApplicationStatus::Ghosted, $applied->fresh()->status);
        $this->assertSame('2026-06-14', $applied->fresh()->latestStatusUpdate->occurred_at->toDateString());
        $this->assertSame(ApplicationService::ghostedNote(30), $applied->fresh()->latestStatusUpdate->notes);
        $this->assertSame(ApplicationStatus::Ghosted, $interviewed->fresh()->status);
        $this->assertSame('2026-05-31', $interviewed->fresh()->latestStatusUpdate->occurred_at->toDateString());
        $this->assertSame(ApplicationStatus::Applied, $recent->fresh()->status);
    }

    public function test_it_uses_the_configured_threshold(): void
    {
        config()->set('resume.ghosted_after_days', 14);
        $application = Application::factory()->applied(now()->subDays(15))->create();

        $this->artisan('applications:mark-ghosted')->assertSuccessful();

        $this->assertSame(ApplicationStatus::Ghosted, $application->fresh()->status);
        $this->assertSame(ApplicationService::ghostedNote(14), $application->fresh()->latestStatusUpdate->notes);
    }

    public function test_it_skips_deleted_applications_and_statuses_that_do_not_wait_on_the_employer(): void
    {
        $deleted = Application::factory()->applied(now()->subDays(60))->create();
        $deleted->delete();

        $others = collect([ApplicationStatus::Interviewing, ApplicationStatus::Offered, ApplicationStatus::Rejected, ApplicationStatus::Withdrawn, ApplicationStatus::Hired])
            ->map(fn (ApplicationStatus $status): Application => $this->withEntry(Application::factory()->applied(now()->subDays(120))->create(), $status, now()->subDays(90)));

        $this->artisan('applications:mark-ghosted')->assertSuccessful();

        $this->assertSame(ApplicationStatus::Applied, Application::withTrashed()->find($deleted->id)->status);
        $this->assertSame(0, ApplicationStatusUpdate::query()->where('application_id', $deleted->id)->where('status', 'ghosted')->count());

        foreach ($others as $application) {
            $this->assertNotSame(ApplicationStatus::Ghosted, $application->fresh()->status);
        }
    }

    public function test_a_dry_run_lists_what_it_would_mark_and_writes_nothing(): void
    {
        $application = Application::factory()->applied(now()->subDays(40))->create(['company_name' => 'Silent Corp', 'position' => 'Engineer']);

        $this->artisan('applications:mark-ghosted', ['--dry-run' => true])
            ->expectsOutputToContain('2026-06-05')
            ->expectsOutputToContain('would be marked ghosted')
            ->assertSuccessful();

        $this->assertSame(ApplicationStatus::Applied, $application->fresh()->status);
        $this->assertSame(1, $application->statusUpdates()->count());

        $this->artisan('applications:mark-ghosted', ['--dry-run' => true])
            ->expectsOutputToContain('Silent Corp')
            ->assertSuccessful();
    }

    public function test_running_twice_records_one_entry(): void
    {
        $application = Application::factory()->applied(now()->subDays(40))->create();

        $this->artisan('applications:mark-ghosted')->assertSuccessful();
        $this->artisan('applications:mark-ghosted')->assertSuccessful();

        $this->assertSame(1, $application->statusUpdates()->where('status', ApplicationStatus::Ghosted)->count());
    }

    public function test_an_application_updated_after_selection_is_not_marked(): void
    {
        $application = Application::factory()->applied(now()->subDays(40))->create();
        $replied = false;

        Application::retrieved(function (Application $retrieved) use ($application, &$replied): void {
            if (! $replied && $retrieved->is($application)) {
                $replied = true;
                app(ApplicationService::class)->addStatusUpdate($application->fresh(), ApplicationStatus::Interviewing, null, now());
            }
        });

        $this->artisan('applications:mark-ghosted')->assertSuccessful();

        $this->assertTrue($replied);
        $this->assertSame(ApplicationStatus::Interviewing, $application->fresh()->status);
        $this->assertSame(0, $application->statusUpdates()->where('status', ApplicationStatus::Ghosted)->count());
    }

    public function test_it_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event): bool => str_contains((string) $event->command, 'applications:mark-ghosted'));

        $this->assertCount(1, $events);
        $this->assertSame('0 3 * * *', $events->first()->expression);
        $this->assertTrue($events->first()->withoutOverlapping);
    }

    private function withEntry(Application $application, ApplicationStatus $status, Carbon $occurredAt): Application
    {
        app(ApplicationService::class)->addStatusUpdate($application, $status, null, $occurredAt);

        return $application->fresh();
    }
}
