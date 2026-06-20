<?php

namespace Tests\Feature;

use App\Enums\TargetedResumeApplicationStatus;
use App\Models\TargetedResume;
use App\Models\TargetedResumeStatusUpdate;
use App\Models\User;
use App\Services\TargetedResumeMetricsService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResumeMetricsTest extends TestCase
{
    use DatabaseTransactions;

    private TargetedResumeMetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('resume.ghosted_after_days', 30);
        $this->service = app(TargetedResumeMetricsService::class);
    }

    /**
     * Create a targeted resume with a chronological set of status updates.
     *
     * @param  array<int, array{0: TargetedResumeApplicationStatus, 1: int}>  $updates  [status, daysAgo]
     */
    private function application(array $updates): TargetedResume
    {
        $resume = TargetedResume::factory()->create();

        foreach ($updates as [$status, $daysAgo]) {
            TargetedResumeStatusUpdate::factory()->create([
                'targeted_resume_id' => $resume->id,
                'status' => $status,
                'occurred_at' => now()->subDays($daysAgo),
            ]);
        }

        return $resume;
    }

    public function test_only_applied_resumes_are_counted(): void
    {
        // Applied
        $this->application([[TargetedResumeApplicationStatus::Applied, 5]]);
        // Draft with no status history — should be ignored
        TargetedResume::factory()->create();

        $metrics = $this->service->build();

        $this->assertSame(1, $metrics['kpis']['totalApplied']);
    }

    public function test_silence_past_threshold_resolves_to_ghosted(): void
    {
        $this->application([[TargetedResumeApplicationStatus::Applied, 40]]);
        $this->application([[TargetedResumeApplicationStatus::Applied, 10]]);

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
            [TargetedResumeApplicationStatus::Applied, 30],
            [TargetedResumeApplicationStatus::Rejected, 20],
        ]);
        // Full progression to accepted
        $this->application([
            [TargetedResumeApplicationStatus::Applied, 60],
            [TargetedResumeApplicationStatus::Interviewing, 50],
            [TargetedResumeApplicationStatus::Interviewed, 45],
            [TargetedResumeApplicationStatus::Offered, 40],
            [TargetedResumeApplicationStatus::Accepted, 35],
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
            [TargetedResumeApplicationStatus::Applied, 30],
            [TargetedResumeApplicationStatus::Rejected, 20],
        ]);

        $metrics = $this->service->build();

        $this->assertSame(10.0, $metrics['cycleTimes']['toFirstResponse']);
        $this->assertSame(10.0, $metrics['cycleTimes']['toRejection']);
        $this->assertNull($metrics['cycleTimes']['toOffer']);
    }

    public function test_timeline_includes_segments_per_application(): void
    {
        $this->application([
            [TargetedResumeApplicationStatus::Applied, 30],
            [TargetedResumeApplicationStatus::Interviewing, 20],
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

        $this->application([[TargetedResumeApplicationStatus::Applied, 5]]);

        $this->actingAs($admin)
            ->get(route('admin.resume.metrics'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('resume/metrics/Index', false)
                ->has('timeline', 1)
                ->where('kpis.totalApplied', 1)
            );
    }
}
