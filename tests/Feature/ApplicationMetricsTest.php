<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Models\ResumeVersion;
use App\Services\ApplicationMetricsService;
use App\Services\ApplicationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The `application-metrics` spec against `ApplicationMetricsService::build()`.
 *
 * The test database may hold applications this suite did not create, so
 * every dated fixture lives in 2001 (with "now" frozen in July 2001) where
 * no real application can be, and the unbounded cases compare against a
 * baseline taken before the fixtures are created.
 */
class ApplicationMetricsTest extends TestCase
{
    use DatabaseTransactions;

    private const int GHOSTED_AFTER_DAYS = 30;

    private const array SECTIONS = ['kpis', 'funnel', 'outcomes', 'overTime', 'cycleTimes', 'timeline'];

    private ApplicationMetricsService $metrics;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('resume.ghosted_after_days', self::GHOSTED_AFTER_DAYS);
        $this->travelTo(Carbon::parse('2001-07-01 12:00:00'));
        $this->metrics = app(ApplicationMetricsService::class);

        $this->assertSame(
            0,
            ApplicationStatusUpdate::query()->where('occurred_at', '<', '2002-01-01 00:00:00')->count(),
            'This suite needs the years up to 2001 free of status-history entries.',
        );
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // Requirement: Metrics count applied Applications
    // ---------------------------------------------------------------------

    public function test_main_resume_application_counted(): void
    {
        $application = app(ApplicationService::class)->createApplied(
            'Build things.',
            'Acme Corp',
            'Staff Engineer',
            resumeVersionId: ResumeVersion::factory()->create()->id,
            occurredAt: Carbon::parse('2001-03-01 09:00:00'),
        );
        $this->entry($application, ApplicationStatus::Interviewing, '2001-03-11 09:00:00');
        $this->entry($application, ApplicationStatus::Offered, '2001-03-21 09:00:00');

        $this->assertNull($application->fresh()->targeted_resume_id);
        $this->assertNull($application->fresh()->ai_conversation_id);

        $metrics = $this->year2001();

        $this->assertSame(
            ['totalApplied' => 1, 'responseRate' => 100.0, 'interviewRate' => 100.0, 'offerRate' => 100.0, 'ghostRate' => 0.0],
            $metrics['kpis'],
        );
        $this->assertSame(
            ['applied' => 1, 'interviewing' => 1, 'interviewed' => 1, 'offered' => 1, 'accepted' => 0],
            $this->funnelCounts($metrics),
        );
        $this->assertSame(['in_progress' => 1], $this->outcomeCounts($metrics));
        $this->assertSame([['period' => '2001-03', 'count' => 1]], $metrics['overTime']);
        $this->assertSame(['toFirstResponse' => 10.0, 'toRejection' => null, 'toOffer' => 20.0], $metrics['cycleTimes']);

        $this->assertCount(1, $metrics['timeline']);
        $row = $metrics['timeline'][0];

        $this->assertSame($application->id, $row['id']);
        $this->assertSame('Acme Corp', $row['company']);
        $this->assertSame('Staff Engineer', $row['position']);
        $this->assertSame(Carbon::parse('2001-03-01 09:00:00')->toIso8601String(), $row['appliedAt']);
        $this->assertSame('in_progress', $row['outcome']);
        $this->assertSame(['applied', 'interviewing', 'offered'], array_column($row['segments'], 'status'));
    }

    public function test_main_resume_and_targeted_resume_applications_are_counted_alike(): void
    {
        $main = Application::factory()->applied(Carbon::parse('2001-03-01 09:00:00'))->create();
        $targeted = Application::factory()->withTargetedResume()->withConversation()->applied(Carbon::parse('2001-03-02 09:00:00'))->create();

        $metrics = $this->year2001();

        $this->assertSame(2, $metrics['kpis']['totalApplied']);
        $this->assertSame(2, $this->funnelCounts($metrics)['applied']);
        $this->assertSame([['period' => '2001-03', 'count' => 2]], $metrics['overTime']);
        $this->assertEqualsCanonicalizing([$main->id, $targeted->id], array_column($metrics['timeline'], 'id'));
    }

    public function test_unapplied_application_excluded(): void
    {
        $baseline = $this->metrics->build();
        $baselineYear = $this->year2001();

        Application::factory()->create();
        Application::factory()->passed()->create();
        Application::factory()->withTargetedResume()->withConversation()->create();
        Application::factory()->passed()->withTargetedResume()->create();

        $this->assertEquals($baseline, $this->metrics->build());
        $this->assertEquals($baselineYear, $this->year2001());
        $this->assertSame(0, $this->year2001()['kpis']['totalApplied']);
    }

    public function test_application_with_history_but_no_applied_entry_is_excluded(): void
    {
        $baseline = $this->metrics->build();

        $application = Application::factory()->create(['status' => ApplicationStatus::Interviewing]);
        $this->entry($application, ApplicationStatus::Interviewing, '2001-03-11 09:00:00');

        $this->assertEquals($baseline, $this->metrics->build());
        $this->assertSame([], $this->year2001()['timeline']);
        $this->assertSame(0, $this->year2001()['kpis']['totalApplied']);
    }

    public function test_application_returned_to_draft_by_deleting_its_applied_entry_is_excluded(): void
    {
        $application = Application::factory()->applied(Carbon::parse('2001-03-01 09:00:00'))->create();
        $this->assertSame(1, $this->year2001()['kpis']['totalApplied']);

        app(ApplicationService::class)->deleteStatusUpdate($application, $application->statusUpdates()->sole());

        $this->assertSame(0, $this->year2001()['kpis']['totalApplied']);
        $this->assertSame([], $this->year2001()['timeline']);
    }

    public function test_soft_deleted_application_excluded(): void
    {
        $baseline = $this->metrics->build();
        $baselineYear = $this->year2001();

        $application = Application::factory()->withConversation()->applied(Carbon::parse('2001-03-01 09:00:00'))->create();
        $this->entry($application, ApplicationStatus::Rejected, '2001-03-06 09:00:00');
        $this->assertSame(1, $this->year2001()['kpis']['totalApplied']);

        app(ApplicationService::class)->delete($application);

        $this->assertEquals($baseline, $this->metrics->build());
        $this->assertEquals($baselineYear, $this->year2001());

        foreach (self::SECTIONS as $section) {
            $this->assertArrayHasKey($section, $this->year2001());
        }
    }

    public function test_application_is_dated_by_its_earliest_applied_entry(): void
    {
        $application = Application::factory()->create(['status' => ApplicationStatus::Applied]);
        $this->entry($application, ApplicationStatus::Applied, '2001-04-10 09:00:00');
        $this->entry($application, ApplicationStatus::Applied, '2001-02-10 09:00:00');
        $this->entry($application, ApplicationStatus::Interviewing, '2001-05-10 09:00:00');

        $february = $this->metrics->build(Carbon::parse('2001-02-01'), Carbon::parse('2001-02-28'));
        $april = $this->metrics->build(Carbon::parse('2001-04-01'), Carbon::parse('2001-04-30'));
        $may = $this->metrics->build(Carbon::parse('2001-05-01'), Carbon::parse('2001-05-31'));

        $this->assertSame(1, $february['kpis']['totalApplied']);
        $this->assertSame([['period' => '2001-02', 'count' => 1]], $february['overTime']);
        $this->assertSame(Carbon::parse('2001-02-10 09:00:00')->toIso8601String(), $february['timeline'][0]['appliedAt']);

        $this->assertSame(0, $april['kpis']['totalApplied']);
        $this->assertSame([], $april['timeline']);
        $this->assertSame(0, $may['kpis']['totalApplied']);

        $this->assertSame([['period' => '2001-02', 'count' => 1]], $this->year2001()['overTime']);
    }

    // ---------------------------------------------------------------------
    // Requirement: Date filtering
    // ---------------------------------------------------------------------

    public function test_default_view_counts_every_applied_application(): void
    {
        $baseline = $this->metrics->build();

        $old = Application::factory()->applied(Carbon::parse('1995-01-15 09:00:00'))->create();
        $recent = Application::factory()->applied(Carbon::parse('2001-06-25 09:00:00'))->create();
        $today = Application::factory()->applied(now())->create();

        $metrics = $this->metrics->build();
        $again = $this->metrics->build(null, null);

        $this->assertSame($baseline['kpis']['totalApplied'] + 3, $metrics['kpis']['totalApplied']);
        $this->assertSame($this->funnelCounts($baseline)['applied'] + 3, $this->funnelCounts($metrics)['applied']);
        $this->assertCount(count($baseline['timeline']) + 3, $metrics['timeline']);
        $this->assertSame(array_sum(array_column($baseline['overTime'], 'count')) + 3, array_sum(array_column($metrics['overTime'], 'count')));
        $this->assertSame(array_sum($this->outcomeCounts($baseline)) + 3, array_sum($this->outcomeCounts($metrics)));

        foreach ([$old, $recent, $today] as $application) {
            $this->assertContains($application->id, array_column($metrics['timeline'], 'id'));
        }

        $this->assertEquals($metrics, $again);
    }

    public function test_custom_range_is_inclusive_of_both_end_dates(): void
    {
        $firstSecond = Application::factory()->applied(Carbon::parse('2001-03-01 00:00:00'))->create();
        $lastSecond = Application::factory()->applied(Carbon::parse('2001-03-31 23:59:59'))->create();
        $justBefore = Application::factory()->applied(Carbon::parse('2001-02-28 23:59:59'))->create();
        $justAfter = Application::factory()->applied(Carbon::parse('2001-04-01 00:00:00'))->create();

        $metrics = $this->metrics->build(Carbon::parse('2001-03-01'), Carbon::parse('2001-03-31'));

        $this->assertSame(2, $metrics['kpis']['totalApplied']);
        $this->assertEqualsCanonicalizing([$firstSecond->id, $lastSecond->id], array_column($metrics['timeline'], 'id'));
        $this->assertNotContains($justBefore->id, array_column($metrics['timeline'], 'id'));
        $this->assertNotContains($justAfter->id, array_column($metrics['timeline'], 'id'));
        $this->assertSame(2, $this->funnelCounts($metrics)['applied']);
        $this->assertSame([['period' => '2001-03', 'count' => 2]], $metrics['overTime']);
        $this->assertSame(2, array_sum($this->outcomeCounts($metrics)));
    }

    public function test_custom_range_treats_both_ends_as_whole_days_whatever_time_they_carry(): void
    {
        $firstSecond = Application::factory()->applied(Carbon::parse('2001-03-01 00:00:00'))->create();
        $lastSecond = Application::factory()->applied(Carbon::parse('2001-03-31 23:59:59'))->create();
        Application::factory()->applied(Carbon::parse('2001-02-28 23:59:59'))->create();
        Application::factory()->applied(Carbon::parse('2001-04-01 00:00:00'))->create();

        $from = Carbon::parse('2001-03-01 18:45:00');
        $to = Carbon::parse('2001-03-31 06:15:00');

        $metrics = $this->metrics->build($from, $to);

        $this->assertEqualsCanonicalizing([$firstSecond->id, $lastSecond->id], array_column($metrics['timeline'], 'id'));
        $this->assertSame('2001-03-01 18:45:00', $from->toDateTimeString(), 'The caller\'s from date must not be mutated.');
        $this->assertSame('2001-03-31 06:15:00', $to->toDateTimeString(), 'The caller\'s to date must not be mutated.');
    }

    public function test_custom_range_of_a_single_day(): void
    {
        $start = Application::factory()->applied(Carbon::parse('2001-03-15 00:00:00'))->create();
        $end = Application::factory()->applied(Carbon::parse('2001-03-15 23:59:59'))->create();
        Application::factory()->applied(Carbon::parse('2001-03-14 23:59:59'))->create();
        Application::factory()->applied(Carbon::parse('2001-03-16 00:00:00'))->create();

        $metrics = $this->metrics->build(Carbon::parse('2001-03-15'), Carbon::parse('2001-03-15'));

        $this->assertEqualsCanonicalizing([$start->id, $end->id], array_column($metrics['timeline'], 'id'));
        $this->assertSame(2, $metrics['kpis']['totalApplied']);
    }

    public function test_open_ended_custom_range_with_only_a_from_date(): void
    {
        $baseline = $this->metrics->build(Carbon::parse('2001-03-15'));

        $before = Application::factory()->applied(Carbon::parse('2001-03-14 23:59:59'))->create();
        $onTheDay = Application::factory()->applied(Carbon::parse('2001-03-15 00:00:00'))->create();
        $after = Application::factory()->applied(Carbon::parse('2001-06-20 10:00:00'))->create();

        $metrics = $this->metrics->build(Carbon::parse('2001-03-15'));
        $ids = array_column($metrics['timeline'], 'id');

        $this->assertSame($baseline['kpis']['totalApplied'] + 2, $metrics['kpis']['totalApplied']);
        $this->assertSame($this->funnelCounts($baseline)['applied'] + 2, $this->funnelCounts($metrics)['applied']);
        $this->assertContains($onTheDay->id, $ids);
        $this->assertContains($after->id, $ids);
        $this->assertNotContains($before->id, $ids);
        $this->assertNotContains('2001-02', array_column($metrics['overTime'], 'period'));
        $this->assertSame(
            ['2001-03' => 1, '2001-06' => 1],
            collect($metrics['overTime'])->whereIn('period', ['2001-03', '2001-06'])->pluck('count', 'period')->all(),
        );
    }

    public function test_open_ended_custom_range_with_only_a_to_date(): void
    {
        $before = Application::factory()->applied(Carbon::parse('1998-11-02 10:00:00'))->create();
        $onTheDay = Application::factory()->applied(Carbon::parse('2001-03-15 23:59:59'))->create();
        $after = Application::factory()->applied(Carbon::parse('2001-03-16 00:00:00'))->create();

        $metrics = $this->metrics->build(null, Carbon::parse('2001-03-15'));

        $this->assertSame(2, $metrics['kpis']['totalApplied']);
        $this->assertEqualsCanonicalizing([$before->id, $onTheDay->id], array_column($metrics['timeline'], 'id'));
        $this->assertNotContains($after->id, array_column($metrics['timeline'], 'id'));
        $this->assertSame(
            [['period' => '1998-11', 'count' => 1], ['period' => '2001-03', 'count' => 1]],
            $metrics['overTime'],
        );
    }

    public function test_period_with_no_applications_returns_zero_counts_and_empty_lists(): void
    {
        Application::factory()->applied(Carbon::parse('2001-03-01 09:00:00'))->create();

        $metrics = $this->metrics->build(Carbon::parse('1990-01-01'), Carbon::parse('1990-12-31'));

        $this->assertSame(self::GHOSTED_AFTER_DAYS, $metrics['ghostedAfterDays']);
        $this->assertSame(0, $metrics['kpis']['totalApplied']);
        $this->assertSame(
            ['applied' => 0, 'interviewing' => 0, 'interviewed' => 0, 'offered' => 0, 'accepted' => 0],
            $this->funnelCounts($metrics),
        );
        $this->assertSame([], $metrics['outcomes']);
        $this->assertSame([], $metrics['overTime']);
        $this->assertSame(['toFirstResponse' => null, 'toRejection' => null, 'toOffer' => null], $metrics['cycleTimes']);
        $this->assertSame([], $metrics['timeline']);

        foreach (['responseRate', 'interviewRate', 'offerRate', 'ghostRate'] as $rate) {
            $this->assertNull($metrics['kpis'][$rate], "{$rate} should be absent, not a misleading 0%, when nothing was applied to.");
        }
    }

    public function test_inverted_range_returns_an_empty_payload_rather_than_an_error(): void
    {
        Application::factory()->applied(Carbon::parse('2001-03-15 09:00:00'))->create();

        $metrics = $this->metrics->build(Carbon::parse('2001-03-31'), Carbon::parse('2001-03-01'));

        $this->assertSame(0, $metrics['kpis']['totalApplied']);
        $this->assertSame([], $metrics['timeline']);
    }

    public function test_range_restricts_every_section_consistently(): void
    {
        $march = Application::factory()->applied(Carbon::parse('2001-03-10 09:00:00'))->create(['company_name' => 'March Co']);
        $this->entry($march, ApplicationStatus::Rejected, '2001-03-15 09:00:00');

        $may = Application::factory()->withTargetedResume()->applied(Carbon::parse('2001-05-10 09:00:00'))->create(['company_name' => 'May Co']);
        $this->entry($may, ApplicationStatus::Interviewing, '2001-05-20 09:00:00');
        $this->entry($may, ApplicationStatus::Interviewed, '2001-05-25 09:00:00');
        $this->entry($may, ApplicationStatus::Offered, '2001-06-09 09:00:00');
        $this->entry($may, ApplicationStatus::Accepted, '2001-06-12 09:00:00');

        $marchOnly = $this->metrics->build(Carbon::parse('2001-03-01'), Carbon::parse('2001-03-31'));

        $this->assertSame(
            ['totalApplied' => 1, 'responseRate' => 100.0, 'interviewRate' => 0.0, 'offerRate' => 0.0, 'ghostRate' => 0.0],
            $marchOnly['kpis'],
        );
        $this->assertSame(
            ['applied' => 1, 'interviewing' => 0, 'interviewed' => 0, 'offered' => 0, 'accepted' => 0],
            $this->funnelCounts($marchOnly),
        );
        $this->assertSame(['rejected' => 1], $this->outcomeCounts($marchOnly));
        $this->assertSame([['period' => '2001-03', 'count' => 1]], $marchOnly['overTime']);
        $this->assertSame(['toFirstResponse' => 5.0, 'toRejection' => 5.0, 'toOffer' => null], $marchOnly['cycleTimes']);
        $this->assertSame([$march->id], array_column($marchOnly['timeline'], 'id'));
        $this->assertSame(['March Co'], array_column($marchOnly['timeline'], 'company'));

        $mayOnly = $this->metrics->build(Carbon::parse('2001-05-01'), Carbon::parse('2001-05-31'));

        $this->assertSame(
            ['totalApplied' => 1, 'responseRate' => 100.0, 'interviewRate' => 100.0, 'offerRate' => 100.0, 'ghostRate' => 0.0],
            $mayOnly['kpis'],
        );
        $this->assertSame(
            ['applied' => 1, 'interviewing' => 1, 'interviewed' => 1, 'offered' => 1, 'accepted' => 1],
            $this->funnelCounts($mayOnly),
        );
        $this->assertSame(['accepted' => 1], $this->outcomeCounts($mayOnly));
        $this->assertSame([['period' => '2001-05', 'count' => 1]], $mayOnly['overTime']);
        $this->assertSame(['toFirstResponse' => 10.0, 'toRejection' => null, 'toOffer' => 30.0], $mayOnly['cycleTimes']);
        $this->assertSame([$may->id], array_column($mayOnly['timeline'], 'id'));
        $this->assertSame(
            ['applied', 'interviewing', 'interviewed', 'offered', 'accepted'],
            array_column($mayOnly['timeline'][0]['segments'], 'status'),
        );

        $both = $this->year2001();

        $this->assertSame(2, $both['kpis']['totalApplied']);
        $this->assertSame(50.0, $both['kpis']['interviewRate']);
        $this->assertSame(
            ['applied' => 2, 'interviewing' => 1, 'interviewed' => 1, 'offered' => 1, 'accepted' => 1],
            $this->funnelCounts($both),
        );
        $this->assertSame(['accepted' => 1, 'rejected' => 1], $this->outcomeCounts($both));
        $this->assertSame([['period' => '2001-03', 'count' => 1], ['period' => '2001-05', 'count' => 1]], $both['overTime']);
        $this->assertSame(['toFirstResponse' => 7.5, 'toRejection' => 5.0, 'toOffer' => 30.0], $both['cycleTimes']);
        $this->assertSame([$may->id, $march->id], array_column($both['timeline'], 'id'));
    }

    public function test_an_application_applied_in_range_keeps_its_later_history_from_outside_the_range(): void
    {
        $application = Application::factory()->applied(Carbon::parse('2001-03-28 09:00:00'))->create();
        $this->entry($application, ApplicationStatus::Interviewing, '2001-04-07 09:00:00');

        $march = $this->metrics->build(Carbon::parse('2001-03-01'), Carbon::parse('2001-03-31'));
        $april = $this->metrics->build(Carbon::parse('2001-04-01'), Carbon::parse('2001-04-30'));

        $this->assertSame(1, $this->funnelCounts($march)['interviewing']);
        $this->assertSame(10.0, $march['cycleTimes']['toFirstResponse']);
        $this->assertCount(2, $march['timeline'][0]['segments']);
        $this->assertSame(0, $april['kpis']['totalApplied']);
        $this->assertSame(0, $this->funnelCounts($april)['interviewing']);
        $this->assertNull($april['cycleTimes']['toFirstResponse']);
    }

    public function test_ghosted_is_judged_against_the_present_not_the_end_of_the_range(): void
    {
        $silent = Application::factory()->applied(Carbon::parse('2001-03-01 09:00:00'))->create();
        $fresh = Application::factory()->applied(now()->subDays(self::GHOSTED_AFTER_DAYS - 1))->create();

        $march = $this->metrics->build(Carbon::parse('2001-03-01'), Carbon::parse('2001-03-15'));

        $this->assertSame(['ghosted' => 1], $this->outcomeCounts($march));
        $this->assertSame(100.0, $march['kpis']['ghostRate']);
        $this->assertSame('ghosted', $march['timeline'][0]['outcome']);
        $this->assertSame(['ghosted'], array_column($march['timeline'][0]['segments'], 'status'));
        $this->assertSame(ApplicationStatus::Applied, $silent->fresh()->status);

        $both = $this->year2001();

        $this->assertSame(['in_progress' => 1, 'ghosted' => 1], $this->outcomeCounts($both));
        $this->assertSame(50.0, $both['kpis']['ghostRate']);
        $this->assertSame('in_progress', collect($both['timeline'])->firstWhere('id', $fresh->id)['outcome']);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function year2001(): array
    {
        return $this->metrics->build(Carbon::parse('2001-01-01'), Carbon::parse('2001-12-31'));
    }

    private function entry(Application $application, ApplicationStatus $status, string $occurredAt): ApplicationStatusUpdate
    {
        $entry = ApplicationStatusUpdate::factory()->create([
            'application_id' => $application->id,
            'status' => $status,
            'occurred_at' => Carbon::parse($occurredAt),
        ]);

        $application->update(['status' => $application->statusUpdates()->get()->last()->status]);

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, int>
     */
    private function funnelCounts(array $metrics): array
    {
        return array_column($metrics['funnel'], 'count', 'stage');
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, int>
     */
    private function outcomeCounts(array $metrics): array
    {
        return array_column($metrics['outcomes'], 'count', 'outcome');
    }
}
