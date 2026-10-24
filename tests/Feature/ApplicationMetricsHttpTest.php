<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Spec `application-metrics` through the dashboard's own route.
 *
 * "Now" is frozen at noon on 1 July 2001 and every dated fixture lives in or
 * before 2001, where no real application can be, so bounded periods can be
 * asserted exactly; unbounded ones are compared against a baseline read
 * before the fixtures are created.
 */
class ApplicationMetricsHttpTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('resume.ghosted_after_days', 30);
        $this->travelTo(Carbon::parse('2001-07-01 12:00:00'));

        $this->admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');

        $this->assertSame(
            0,
            ApplicationStatusUpdate::query()->where('occurred_at', '<', '2002-01-01 00:00:00')->count(),
            'This suite needs the years up to 2001 free of status-history entries.',
        );
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // Requirement: Date filtering
    // ---------------------------------------------------------------------

    public function test_default_view(): void
    {
        $baseline = $this->metrics();

        $old = $this->applied('1995-01-15 09:00:00');
        $recent = $this->applied('2001-06-25 09:00:00');
        $today = $this->applied('2001-07-01 08:00:00');
        Application::factory()->create();
        Application::factory()->passed()->create();
        $this->applied('2001-06-20 09:00:00')->delete();

        $metrics = $this->metrics();

        $this->assertSame(['range' => 'all', 'from' => null, 'to' => null], $metrics['filter']);
        $this->assertSame($baseline['kpis']['totalApplied'] + 3, $metrics['kpis']['totalApplied']);
        $this->assertCount(count($baseline['timeline']) + 3, $metrics['timeline']);
        $this->assertSame($this->funnel($baseline)['applied'] + 3, $this->funnel($metrics)['applied']);
        $this->assertSame(array_sum(array_column($baseline['overTime'], 'count')) + 3, array_sum(array_column($metrics['overTime'], 'count')));
        $this->assertSame(array_sum(array_column($baseline['outcomes'], 'count')) + 3, array_sum(array_column($metrics['outcomes'], 'count')));

        foreach ([$old, $recent, $today] as $application) {
            $this->assertContains($application->id, array_column($metrics['timeline'], 'id'));
        }
    }

    public function test_default_view_is_also_what_an_explicit_all_time_or_cleared_dates_give(): void
    {
        $this->applied('2001-06-25 09:00:00');
        $default = $this->metrics();

        $this->assertEquals($default, $this->metrics(['range' => 'all']));
        $this->assertEquals($default, $this->metrics(['from' => '', 'to' => '']));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function presetProvider(): array
    {
        return [
            'last 30 days' => ['30d', '2001-06-01'],
            'last 90 days' => ['90d', '2001-04-02'],
            'this year' => ['ytd', '2001-01-01'],
        ];
    }

    #[DataProvider('presetProvider')]
    public function test_preset_selected(string $range, string $from): void
    {
        $firstSecond = $this->applied("{$from} 00:00:00");
        $laterToday = $this->applied('2001-07-01 23:59:59');
        $justBefore = $this->applied(Carbon::parse("{$from} 00:00:00")->subSecond()->toDateTimeString());
        $tomorrow = $this->applied('2001-07-02 00:00:00');
        $this->entry($firstSecond, ApplicationStatus::Interviewing, '2001-07-01 09:00:00');
        $this->entry($justBefore, ApplicationStatus::Rejected, '2001-06-30 09:00:00');

        $metrics = $this->metrics(['range' => $range]);

        $this->assertSame(['range' => $range, 'from' => $from, 'to' => '2001-07-01'], $metrics['filter']);
        $this->assertEqualsCanonicalizing([$firstSecond->id, $laterToday->id], array_column($metrics['timeline'], 'id'));
        $this->assertNotContains($justBefore->id, array_column($metrics['timeline'], 'id'));
        $this->assertNotContains($tomorrow->id, array_column($metrics['timeline'], 'id'));

        $this->assertSame(2, $metrics['kpis']['totalApplied']);
        $this->assertEquals(50.0, $metrics['kpis']['interviewRate']);
        $this->assertSame(['applied' => 2, 'interviewing' => 1, 'interviewed' => 0, 'offered' => 0, 'accepted' => 0], $this->funnel($metrics));
        $this->assertSame(2, array_sum(array_column($metrics['outcomes'], 'count')));
        $this->assertNotContains('rejected', array_column($metrics['outcomes'], 'outcome'));
        $this->assertSame(2, array_sum(array_column($metrics['overTime'], 'count')));
        $this->assertNull($metrics['cycleTimes']['toRejection']);
        $this->assertNotNull($metrics['cycleTimes']['toFirstResponse']);
    }

    public function test_custom_range(): void
    {
        $firstSecond = $this->applied('2001-03-01 00:00:00');
        $lastSecond = $this->applied('2001-03-31 23:59:59');
        $before = $this->applied('2001-02-28 23:59:59');
        $after = $this->applied('2001-04-01 00:00:00');
        $this->entry($firstSecond, ApplicationStatus::Rejected, '2001-03-06 00:00:00');
        $this->entry($after, ApplicationStatus::Interviewing, '2001-04-11 00:00:00');
        $this->entry($after, ApplicationStatus::Offered, '2001-04-21 00:00:00');

        $metrics = $this->metrics(['from' => '2001-03-01', 'to' => '2001-03-31']);

        $this->assertSame(['range' => 'custom', 'from' => '2001-03-01', 'to' => '2001-03-31'], $metrics['filter']);
        $this->assertNotContains($metrics['filter']['range'], ['30d', '90d', 'ytd', 'all']);
        $this->assertEqualsCanonicalizing([$firstSecond->id, $lastSecond->id], array_column($metrics['timeline'], 'id'));
        $this->assertNotContains($before->id, array_column($metrics['timeline'], 'id'));
        $this->assertNotContains($after->id, array_column($metrics['timeline'], 'id'));

        $this->assertEquals(
            ['totalApplied' => 2, 'responseRate' => 50.0, 'interviewRate' => 0.0, 'offerRate' => 0.0, 'ghostRate' => 50.0],
            $metrics['kpis'],
        );
        $this->assertSame(['applied' => 2, 'interviewing' => 0, 'interviewed' => 0, 'offered' => 0, 'accepted' => 0], $this->funnel($metrics));
        $this->assertSame(['rejected' => 1, 'ghosted' => 1], array_column($metrics['outcomes'], 'count', 'outcome'));
        $this->assertSame([['period' => '2001-03', 'count' => 2]], $metrics['overTime']);
        $this->assertEquals(['toFirstResponse' => 5.0, 'toRejection' => 5.0, 'toOffer' => null], $metrics['cycleTimes']);
    }

    public function test_custom_range_of_a_single_day(): void
    {
        $onTheDay = $this->applied('2001-03-15 13:00:00');
        $this->applied('2001-03-14 23:59:59');
        $this->applied('2001-03-16 00:00:00');

        $metrics = $this->metrics(['from' => '2001-03-15', 'to' => '2001-03-15']);

        $this->assertSame(['range' => 'custom', 'from' => '2001-03-15', 'to' => '2001-03-15'], $metrics['filter']);
        $this->assertSame([$onTheDay->id], array_column($metrics['timeline'], 'id'));
    }

    public function test_custom_range_overrides_a_preset_sent_with_it(): void
    {
        $inRange = $this->applied('2001-03-10 09:00:00');
        $inPreset = $this->applied('2001-06-25 09:00:00');

        $both = $this->metrics(['range' => '30d', 'from' => '2001-03-01', 'to' => '2001-03-31']);
        $toOnly = $this->metrics(['range' => '30d', 'to' => '2001-03-31']);

        $this->assertSame(['range' => 'custom', 'from' => '2001-03-01', 'to' => '2001-03-31'], $both['filter']);
        $this->assertSame([$inRange->id], array_column($both['timeline'], 'id'));
        $this->assertSame(['range' => 'custom', 'from' => null, 'to' => '2001-03-31'], $toOnly['filter']);
        $this->assertSame([$inRange->id], array_column($toOnly['timeline'], 'id'));
        $this->assertNotContains($inPreset->id, array_column($toOnly['timeline'], 'id'));
    }

    public function test_open_ended_custom_range(): void
    {
        $query = ['from' => '2001-03-15'];
        $baseline = $this->metrics($query);

        $before = $this->applied('2001-03-14 23:59:59');
        $onTheDay = $this->applied('2001-03-15 00:00:00');
        $after = $this->applied('2001-06-20 10:00:00');

        $metrics = $this->metrics($query);
        $ids = array_column($metrics['timeline'], 'id');

        $this->assertSame(['range' => 'custom', 'from' => '2001-03-15', 'to' => null], $metrics['filter']);
        $this->assertSame($baseline['kpis']['totalApplied'] + 2, $metrics['kpis']['totalApplied']);
        $this->assertSame($this->funnel($baseline)['applied'] + 2, $this->funnel($metrics)['applied']);
        $this->assertContains($onTheDay->id, $ids);
        $this->assertContains($after->id, $ids);
        $this->assertNotContains($before->id, $ids);
    }

    public function test_open_ended_custom_range_with_only_a_to_date(): void
    {
        $before = $this->applied('1998-11-02 10:00:00');
        $onTheDay = $this->applied('2001-03-15 23:59:59');
        $after = $this->applied('2001-03-16 00:00:00');

        $metrics = $this->metrics(['to' => '2001-03-15']);

        $this->assertSame(['range' => 'custom', 'from' => null, 'to' => '2001-03-15'], $metrics['filter']);
        $this->assertEqualsCanonicalizing([$before->id, $onTheDay->id], array_column($metrics['timeline'], 'id'));
        $this->assertNotContains($after->id, array_column($metrics['timeline'], 'id'));
        $this->assertSame(2, $metrics['kpis']['totalApplied']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2?: string}>
     */
    public static function invalidRangeProvider(): array
    {
        return [
            'from after to' => [['from' => '2001-03-31', 'to' => '2001-03-01'], 'to', 'The end date cannot be before the start date.'],
            'from after to, with a preset' => [['range' => '30d', 'from' => '2001-03-31', 'to' => '2001-03-01'], 'to', 'The end date cannot be before the start date.'],
            'from that is a word' => [['from' => 'yesterday'], 'from', 'The start date must be a date in YYYY-MM-DD form.'],
            'from in another format' => [['from' => '03/01/2001'], 'from', 'The start date must be a date in YYYY-MM-DD form.'],
            'from with a time' => [['from' => '2001-03-01 10:00:00'], 'from', 'The start date must be a date in YYYY-MM-DD form.'],
            'from that is an impossible date' => [['from' => '2001-13-45'], 'from', 'The start date must be a date in YYYY-MM-DD form.'],
            'from that is a list' => [['from' => ['2001-03-01']], 'from'],
            'to that is a word' => [['to' => 'tomorrow'], 'to', 'The end date must be a date in YYYY-MM-DD form.'],
            'to that is an impossible date' => [['from' => '2001-03-01', 'to' => '2001-02-30'], 'to'],
            'unknown preset' => [['range' => 'fortnight'], 'range', 'Choose one of the listed periods.'],
            'custom is not a preset' => [['range' => 'custom'], 'range', 'Choose one of the listed periods.'],
            'preset that is a list' => [['range' => ['30d']], 'range'],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    #[DataProvider('invalidRangeProvider')]
    public function test_invalid_range(array $query, string $errorKey, ?string $message = null): void
    {
        $this->applied('2001-03-10 09:00:00');

        $visit = $this->actingAs($this->admin)
            ->from(route('admin.resume.metrics'))
            ->get(route('admin.resume.metrics', $query));

        $visit->assertRedirect(route('admin.resume.metrics'))->assertSessionHasErrors([$errorKey]);
        $this->assertStringNotContainsString('totalApplied', (string) $visit->getContent());

        $inertia = $this->actingAs($this->admin)
            ->from(route('admin.resume.metrics'))
            ->get(route('admin.resume.metrics', $query), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create('/')),
                'X-Requested-With' => 'XMLHttpRequest',
            ]);

        $inertia->assertRedirect(route('admin.resume.metrics'))->assertSessionHasErrors([$errorKey]);
        $this->assertStringNotContainsString('totalApplied', (string) $inertia->getContent());

        $json = $this->actingAs($this->admin)
            ->getJson(route('admin.resume.metrics', $query))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$errorKey])
            ->assertJsonMissingPath('kpis')
            ->assertJsonMissingPath('timeline');

        if ($message !== null) {
            $json->assertJsonPath("errors.{$errorKey}.0", $message);
        }
    }

    public function test_period_with_no_applications(): void
    {
        $this->applied('2001-03-10 09:00:00');

        $this->actingAs($this->admin)
            ->get(route('admin.resume.metrics', ['from' => '1990-01-01', 'to' => '1990-12-31']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/metrics/Index', false)
                ->where('filter', ['range' => 'custom', 'from' => '1990-01-01', 'to' => '1990-12-31'])
                ->where('ghostedAfterDays', 30)
                ->where('kpis', ['totalApplied' => 0, 'responseRate' => null, 'interviewRate' => null, 'offerRate' => null, 'ghostRate' => null])
                ->where('funnel', fn ($funnel): bool => collect($funnel)->pluck('count', 'stage')->all() === ['applied' => 0, 'interviewing' => 0, 'interviewed' => 0, 'offered' => 0, 'accepted' => 0])
                ->where('outcomes', [])
                ->where('overTime', [])
                ->where('cycleTimes', ['toFirstResponse' => null, 'toRejection' => null, 'toOffer' => null])
                ->where('timeline', [])
            );
    }

    public function test_reload_keeps_the_period(): void
    {
        $this->applied('2001-03-10 09:00:00');
        $url = route('admin.resume.metrics', ['from' => '2001-03-01', 'to' => '2001-03-31']);

        $first = $this->metricsAt($url);
        $second = $this->metricsAt($url);

        $this->assertSame(['range' => 'custom', 'from' => '2001-03-01', 'to' => '2001-03-31'], $first['filter']);
        $this->assertEquals($first, $second);

        $preset = route('admin.resume.metrics', ['range' => '90d']);

        $this->assertSame('90d', $this->metricsAt($preset)['filter']['range']);
        $this->assertEquals($this->metricsAt($preset), $this->metricsAt($preset));
    }

    // ---------------------------------------------------------------------
    // Requirement: Metrics count applied Applications
    // ---------------------------------------------------------------------

    public function test_main_resume_application_counted(): void
    {
        $main = $this->applied('2001-03-10 09:00:00');
        $targeted = Application::factory()->withTargetedResume()->withConversation()->applied(Carbon::parse('2001-03-12 09:00:00'))->create();

        $metrics = $this->metrics(['from' => '2001-03-01', 'to' => '2001-03-31']);

        $this->assertNull($main->targeted_resume_id);
        $this->assertEqualsCanonicalizing([$main->id, $targeted->id], array_column($metrics['timeline'], 'id'));
        $this->assertSame(2, $metrics['kpis']['totalApplied']);
        $this->assertSame(2, $this->funnel($metrics)['applied']);
        $this->assertSame([['period' => '2001-03', 'count' => 2]], $metrics['overTime']);

        $row = collect($metrics['timeline'])->firstWhere('id', $main->id);

        $this->assertSame($main->company_name, $row['company']);
        $this->assertSame($main->position, $row['position']);
    }

    public function test_unapplied_application_excluded(): void
    {
        $baseline = $this->metrics();

        Application::factory()->create();
        Application::factory()->passed()->create();
        Application::factory()->withTargetedResume()->withConversation()->create();

        $this->assertEquals($baseline, $this->metrics());
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * The dashboard's props for a query string.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function metrics(array $query = []): array
    {
        return $this->metricsAt(route('admin.resume.metrics', $query));
    }

    /**
     * @return array<string, mixed>
     */
    private function metricsAt(string $url): array
    {
        $props = [];

        $this->actingAs($this->admin)
            ->get($url)
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props): Assert {
                $props = $page->component('resume/metrics/Index', false)->toArray()['props'];

                return $page;
            });

        return array_intersect_key($props, array_flip(['ghostedAfterDays', 'kpis', 'funnel', 'outcomes', 'overTime', 'cycleTimes', 'timeline', 'filter']));
    }

    private function applied(string $occurredAt): Application
    {
        return Application::factory()->applied(Carbon::parse($occurredAt))->create();
    }

    private function entry(Application $application, ApplicationStatus $status, string $occurredAt): void
    {
        ApplicationStatusUpdate::factory()->create([
            'application_id' => $application->id,
            'status' => $status,
            'occurred_at' => Carbon::parse($occurredAt),
        ]);

        $application->update(['status' => $status]);
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, int>
     */
    private function funnel(array $metrics): array
    {
        return array_column($metrics['funnel'], 'count', 'stage');
    }
}
