<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationStatusUpdate;
use App\Models\User;
use App\Services\ApplicationMetricsService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResumeMetricsTest extends TestCase
{
    use DatabaseTransactions;

    private ApplicationMetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('resume.ghosted_after_days', 30);
        $this->service = app(ApplicationMetricsService::class);
    }

    /**
     * Create an application with a chronological set of status updates.
     *
     * @param  array<int, array{0: ApplicationStatus, 1: int}>  $updates  [status, daysAgo]
     */
    private function application(array $updates): Application
    {
        $application = Application::factory()->create();

        foreach ($updates as [$status, $daysAgo]) {
            ApplicationStatusUpdate::factory()->create([
                'application_id' => $application->id,
                'status' => $status,
                'occurred_at' => now()->subDays($daysAgo),
            ]);
        }

        $application->update(['status' => end($updates)[0]]);

        return $application;
    }

    public function test_only_applied_resumes_are_counted(): void
    {
        // Applied
        $this->application([[ApplicationStatus::Applied, 5]]);
        // Draft with no status history — should be ignored
        Application::factory()->withTargetedResume()->create();

        $metrics = $this->service->build();

        $this->assertSame(1, $metrics['kpis']['totalApplied']);
    }

    public function test_silence_past_threshold_resolves_to_ghosted(): void
    {
        $this->application([[ApplicationStatus::Applied, 40]]);
        $this->application([[ApplicationStatus::Applied, 10]]);

        $metrics = $this->service->build();
        $outcomes = collect($metrics['outcomes'])->keyBy('outcome');

        $this->assertSame(1, $outcomes->get('ghosted')['count']);
        $this->assertSame(1, $outcomes->get('in_progress')['count']);
        $this->assertSame(50.0, $metrics['kpis']['ghostRate']);
    }

    public function test_funnel_and_outcome_counts(): void
    {
        // Rejected after applying
        $this->application([
            [ApplicationStatus::Applied, 30],
            [ApplicationStatus::Rejected, 20],
        ]);
        // Full progression to accepted
        $this->application([
            [ApplicationStatus::Applied, 60],
            [ApplicationStatus::Interviewing, 50],
            [ApplicationStatus::Interviewed, 45],
            [ApplicationStatus::Offered, 40],
            [ApplicationStatus::Accepted, 35],
        ]);

        $metrics = $this->service->build();
        $funnel = collect($metrics['funnel'])->keyBy('stage');
        $outcomes = collect($metrics['outcomes'])->keyBy('outcome');

        $this->assertSame(2, $funnel->get('applied')['count']);
        $this->assertSame(1, $funnel->get('interviewing')['count']);
        $this->assertSame(1, $funnel->get('offered')['count']);
        $this->assertSame(1, $funnel->get('accepted')['count']);

        $this->assertSame(1, $outcomes->get('accepted')['count']);
        $this->assertSame(1, $outcomes->get('rejected')['count']);
    }

    public function test_cycle_times_are_averaged(): void
    {
        $this->application([
            [ApplicationStatus::Applied, 30],
            [ApplicationStatus::Rejected, 20],
        ]);

        $metrics = $this->service->build();

        $this->assertSame(10.0, $metrics['cycleTimes']['toFirstResponse']);
        $this->assertSame(10.0, $metrics['cycleTimes']['toRejection']);
        $this->assertNull($metrics['cycleTimes']['toOffer']);
    }

    public function test_timeline_includes_segments_per_application(): void
    {
        $this->application([
            [ApplicationStatus::Applied, 30],
            [ApplicationStatus::Interviewing, 20],
        ]);

        $metrics = $this->service->build();

        $this->assertCount(1, $metrics['timeline']);
        $this->assertCount(2, $metrics['timeline'][0]['segments']);
        $this->assertSame('applied', $metrics['timeline'][0]['segments'][0]['status']);
    }

    public function test_metrics_page_renders_for_authorized_user(): void
    {
        $admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $admin->givePermissionTo('edit-resume');

        $this->application([[ApplicationStatus::Applied, 5]]);

        $this->actingAs($admin)
            ->get(route('admin.resume.metrics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/metrics/Index', false)
                ->has('timeline', 1)
                ->where('kpis.totalApplied', 1)
            );
    }

    private function metricsAdmin(): User
    {
        $admin = User::factory()->create();
        Permission::firstOrCreate(['name' => 'edit-resume']);
        $admin->givePermissionTo('edit-resume');

        return $admin;
    }

    public function test_metrics_page_defaults_to_all_time(): void
    {
        $this->actingAs($this->metricsAdmin())
            ->get(route('admin.resume.metrics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filter', ['range' => 'all', 'from' => null, 'to' => null])
                ->has('ghostedAfterDays')
                ->has('kpis')
                ->has('funnel')
                ->has('outcomes')
                ->has('overTime')
                ->has('cycleTimes')
                ->has('timeline')
            );
    }

    public function test_metrics_page_resolves_presets_server_side(): void
    {
        $this->travelTo('2026-08-15 10:00:00');

        $inLastMonth = $this->application([[ApplicationStatus::Applied, 5]]);
        $inLastQuarter = $this->application([[ApplicationStatus::Applied, 60]]);
        $lastYear = $this->application([[ApplicationStatus::Applied, 400]]);

        $admin = $this->metricsAdmin();

        $expected = [
            '30d' => ['from' => '2026-07-16', 'to' => '2026-08-15', 'ids' => [$inLastMonth->id]],
            '90d' => ['from' => '2026-05-17', 'to' => '2026-08-15', 'ids' => [$inLastMonth->id, $inLastQuarter->id]],
            'ytd' => ['from' => '2026-01-01', 'to' => '2026-08-15', 'ids' => [$inLastMonth->id, $inLastQuarter->id]],
            'all' => ['from' => null, 'to' => null, 'ids' => [$inLastMonth->id, $inLastQuarter->id, $lastYear->id]],
        ];

        foreach ($expected as $range => $period) {
            $this->actingAs($admin)
                ->get(route('admin.resume.metrics', ['range' => $range]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('filter', ['range' => $range, 'from' => $period['from'], 'to' => $period['to']])
                    ->where('kpis.totalApplied', count($period['ids']))
                    ->has('timeline', count($period['ids']))
                );
        }
    }

    public function test_metrics_page_custom_range_overrides_the_preset(): void
    {
        $this->travelTo('2026-08-15 10:00:00');

        $this->application([[ApplicationStatus::Applied, 5]]);
        $this->application([[ApplicationStatus::Applied, 60]]);

        $admin = $this->metricsAdmin();

        $this->actingAs($admin)
            ->get(route('admin.resume.metrics', ['range' => '30d', 'from' => '2026-06-01', 'to' => '2026-06-30']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filter', ['range' => 'custom', 'from' => '2026-06-01', 'to' => '2026-06-30'])
                ->where('kpis.totalApplied', 1)
            );

        $this->actingAs($admin)
            ->get(route('admin.resume.metrics', ['from' => '2026-08-01']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filter', ['range' => 'custom', 'from' => '2026-08-01', 'to' => null])
                ->where('kpis.totalApplied', 1)
            );

        $this->actingAs($admin)
            ->get(route('admin.resume.metrics', ['to' => '2026-06-30']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filter', ['range' => 'custom', 'from' => null, 'to' => '2026-06-30'])
                ->where('kpis.totalApplied', 1)
            );
    }

    public function test_metrics_page_rejects_an_invalid_period(): void
    {
        $admin = $this->metricsAdmin();

        $invalid = [
            'range' => ['range' => 'fortnight'],
            'from' => ['from' => 'yesterday'],
            'to' => ['from' => '2026-06-30', 'to' => '2026-06-01'],
        ];

        foreach ($invalid as $field => $query) {
            $this->actingAs($admin)
                ->from(route('admin.resume.metrics'))
                ->get(route('admin.resume.metrics', $query))
                ->assertRedirect(route('admin.resume.metrics'))
                ->assertSessionHasErrors([$field]);

            $this->actingAs($admin)
                ->getJson(route('admin.resume.metrics', $query))
                ->assertStatus(422)
                ->assertJsonValidationErrors([$field]);
        }
    }
}
