<?php

namespace Tests\Feature;

use App\Contracts\ResumeVersionServiceContract;
use App\Models\ResumeVersion;
use App\Models\User;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResumeDownloadOnDemandTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Save User',
            'email' => 'resume-download-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
        ]);

        Permission::firstOrCreate(['name' => 'save-resume']);
        Permission::firstOrCreate(['name' => 'read-resume']);
        $this->user->givePermissionTo('save-resume');
        $this->user->givePermissionTo('read-resume');
    }

    public function test_downloading_docx_generates_it_when_missing_and_logs_the_download(): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);

        $fakePath = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        file_put_contents($fakePath, 'fake docx');

        $versionService = $this->createMock(ResumeVersionServiceContract::class);
        $versionService->method('ensureDocx')->willReturn([
            'success' => true,
            'path' => $fakePath,
            'served_cached_document' => false,
        ]);
        $versionService->method('getCurrentVersion')->willReturn('2026.1.0');
        $this->app->instance(ResumeVersionServiceContract::class, $versionService);

        $response = $this->actingAs($this->user)->get(route('resume.download.docx'));

        $response->assertOk();

        $this->assertDatabaseHas('document_downloads', [
            'type' => 'docx',
            'served_cached_document' => false,
        ]);
    }

    public function test_downloading_docx_reports_the_generation_error_when_it_fails(): void
    {
        ResumeVersion::factory()->create(['is_current' => true]);

        $versionService = $this->createMock(ResumeVersionServiceContract::class);
        $versionService->method('ensureDocx')->willReturn([
            'success' => false,
            'error' => 'Template missing.',
        ]);
        $this->app->instance(ResumeVersionServiceContract::class, $versionService);

        $response = $this->actingAs($this->user)->get(route('resume.download.docx'));

        $response->assertNotFound();
    }

    public function test_delete_after_serve_mode_deletes_the_file_and_clears_the_column_after_download(): void
    {
        config(['resume.document_retention_mode' => 'delete_after_serve']);

        $current = ResumeVersion::factory()->create(['is_current' => true]);

        $fakePath = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        file_put_contents($fakePath, 'fake docx');

        $versionService = $this->createMock(ResumeVersionServiceContract::class);
        $versionService->method('ensureDocx')->willReturn([
            'success' => true,
            'path' => $fakePath,
            'served_cached_document' => false,
        ]);
        $versionService->method('getCurrentVersion')->willReturn($current->version);
        $this->app->instance(ResumeVersionServiceContract::class, $versionService);

        $response = $this->actingAs($this->user)->get(route('resume.download.docx'));

        $response->assertOk();

        $this->assertNull($current->fresh()->docx_path);
        $this->assertTrue($response->baseResponse->shouldDeleteFileAfterSend());
    }
}
