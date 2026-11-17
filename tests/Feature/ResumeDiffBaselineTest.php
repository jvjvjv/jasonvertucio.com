<?php

namespace Tests\Feature;

use App\Models\ResumeEditCandidate;
use App\Models\ResumeVersion;
use App\Services\Resume\Diff\ResumeBaseline;
use App\Services\Resume\Diff\ResumeDiffBaseline;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResumeDiffBaselineTest extends TestCase
{
    use DatabaseTransactions;

    private function version(string $name, bool $isCurrent = false): ResumeVersion
    {
        $version = ResumeVersion::factory()->create(['is_current' => $isCurrent]);
        $version->personalInfo()->create(['name' => $name, 'title' => 'Engineer', 'email' => 'jason@example.com']);

        return $version;
    }

    private function resolve(ResumeEditCandidate $candidate, ?string $requested = null): ResumeBaseline
    {
        return app(ResumeDiffBaseline::class)->resolve($candidate->fresh(), $requested);
    }

    protected function setUp(): void
    {
        parent::setUp();
        ResumeVersion::query()->update(['is_current' => false]);
    }

    public function test_the_default_baseline_is_the_version_the_draft_branched_from(): void
    {
        $base = $this->version('Base Name', isCurrent: true);
        $candidate = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id]);

        $baseline = $this->resolve($candidate);

        $this->assertSame('base', $baseline->mode);
        $this->assertSame('Base Name', $baseline->data['personal']['name']);
        $this->assertStringContainsString($base->version, $baseline->label);
        $this->assertFalse($baseline->fellBack);
    }

    public function test_live_is_offered_only_for_a_stale_draft(): void
    {
        $base = $this->version('Base Name', isCurrent: true);
        $candidate = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id]);

        $this->assertArrayNotHasKey('live', $this->resolve($candidate)->available);

        $base->update(['is_current' => false]);
        $this->version('Live Name', isCurrent: true);
        $baseline = $this->resolve($candidate, 'live');

        $this->assertSame('live', $baseline->mode);
        $this->assertSame('Live Name', $baseline->data['personal']['name']);
        $this->assertArrayHasKey('live', $baseline->available);
    }

    public function test_previous_compares_with_the_nearest_earlier_revision(): void
    {
        $base = $this->version('Base Name', isCurrent: true);
        $first = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => 1, 'snapshot' => ['personal' => ['name' => 'Revision One']]]);
        ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => 2, 'snapshot' => ['personal' => ['name' => 'Revision Two']], 'status' => 'approved']);
        $third = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id, 'revision_number' => 3]);

        $baseline = $this->resolve($third, 'previous');

        $this->assertSame('previous', $baseline->mode);
        $this->assertSame('Revision Two', $baseline->data['personal']['name']);
        $this->assertSame('revision #2', $baseline->label);
        $this->assertArrayNotHasKey('previous', $this->resolve($first)->available);
    }

    public function test_an_unavailable_or_unknown_baseline_falls_back_to_base(): void
    {
        $base = $this->version('Base Name', isCurrent: true);
        $candidate = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id]);

        foreach (['previous', 'live', 'sideways'] as $requested) {
            $baseline = $this->resolve($candidate, $requested);

            $this->assertSame('base', $baseline->mode, $requested);
            $this->assertTrue($baseline->fellBack, $requested);
        }
    }

    public function test_a_base_version_that_cannot_be_loaded_compares_with_an_empty_resume(): void
    {
        $base = $this->version('Base Name');
        $candidate = ResumeEditCandidate::factory()->create(['base_resume_version_id' => $base->id]);
        $candidate->setRelation('baseResumeVersion', null);

        $baseline = app(ResumeDiffBaseline::class)->resolve($candidate, null);

        $this->assertSame('base', $baseline->mode);
        $this->assertSame([], $baseline->data);
        $this->assertStringContainsString('no longer exists', $baseline->label);
    }
}
