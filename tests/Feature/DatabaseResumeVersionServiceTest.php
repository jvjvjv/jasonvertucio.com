<?php

namespace Tests\Feature;

use App\Models\ResumeVersion;
use App\Services\DatabaseResumeDataService;
use App\Services\DatabaseResumeVersionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use RuntimeException;
use Tests\TestCase;

class DatabaseResumeVersionServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected DatabaseResumeVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DatabaseResumeVersionService(new DatabaseResumeDataService);
    }

    public function test_get_current_version_returns_default_when_none_set(): void
    {
        $this->assertEquals('0.0.0', $this->service->getCurrentVersion());
    }

    public function test_get_current_version_returns_current_version(): void
    {
        ResumeVersion::factory()->create(['version' => '2026.1.0', 'is_current' => true]);

        $this->assertEquals('2026.1.0', $this->service->getCurrentVersion());
    }

    public function test_set_version_creates_and_sets_current(): void
    {
        $this->service->setVersion('2026.2.0');

        $version = ResumeVersion::where('version', '2026.2.0')->first();
        $this->assertNotNull($version);
        $this->assertTrue($version->is_current);
    }

    public function test_set_version_unsets_previous_current(): void
    {
        ResumeVersion::factory()->create(['version' => '2026.1.0', 'is_current' => true]);

        $this->service->setVersion('2026.2.0');

        $old = ResumeVersion::where('version', '2026.1.0')->first();
        $this->assertFalse($old->is_current);

        $new = ResumeVersion::where('version', '2026.2.0')->first();
        $this->assertTrue($new->is_current);
    }

    public function test_set_version_rejects_invalid_format(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->setVersion('invalid');
    }

    public function test_set_version_rejects_partial_version(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->setVersion('2026.1');
    }

    public function test_set_version_reuses_existing_version_record(): void
    {
        $existing = ResumeVersion::factory()->create(['version' => '2026.1.0', 'is_current' => false]);

        $this->service->setVersion('2026.1.0');

        $this->assertEquals(1, ResumeVersion::where('version', '2026.1.0')->count());
        $this->assertTrue($existing->fresh()->is_current);
    }

    public function test_get_available_versions_returns_empty_when_no_docx_files(): void
    {
        ResumeVersion::factory()->create(['version' => '9999.9.9']);

        $versions = $this->service->getAvailableVersions();

        // The 9999.9.9 version should not appear since no DOCX file exists for it
        $hasTestVersion = collect($versions)->contains(fn ($v) => $v['version'] === '9999.9.9');
        $this->assertFalse($hasTestVersion);
    }
}
