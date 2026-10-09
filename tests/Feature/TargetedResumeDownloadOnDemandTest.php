<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ResumeVersion;
use App\Models\TargetedResume;
use App\Models\User;
use App\Services\TargetedResumeDocumentService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TargetedResumeDownloadOnDemandTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'targeted-download-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
        ]);

        Permission::firstOrCreate(['name' => 'edit-resume']);
        $this->admin->givePermissionTo('edit-resume');
    }

    public function test_regenerate_clears_cached_documents_without_generating_new_ones(): void
    {
        $docxPath = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        $pdfPath = tempnam(sys_get_temp_dir(), 'pdf-').'.pdf';
        file_put_contents($docxPath, 'stale');
        file_put_contents($pdfPath, 'stale');

        $targetedResume = TargetedResume::factory()->create([
            'docx_path' => $docxPath,
            'pdf_path' => $pdfPath,
        ]);

        $application = Application::factory()->create(['targeted_resume_id' => $targetedResume->id]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.resume.targeted.regenerate', $targetedResume));

        $response->assertRedirect(route('admin.resume.applications.show', $application));

        $this->assertFileDoesNotExist($docxPath);
        $this->assertFileDoesNotExist($pdfPath);

        $targetedResume->refresh();
        $this->assertNull($targetedResume->docx_path);
        $this->assertNull($targetedResume->pdf_path);
    }

    public function test_download_generates_the_document_when_missing_and_logs_it(): void
    {
        $resumeVersion = ResumeVersion::factory()->create(['is_current' => true]);
        $resumeVersion->personalInfo()->create([
            'name' => 'Jason Vertucio',
            'title' => 'Engineer',
            'email' => 'jason@example.com',
        ]);

        $targetedResume = TargetedResume::factory()->create([
            'resume_version_id' => $resumeVersion->id,
            'tailored_data' => [
                'title' => 'Senior Engineer',
                'content' => "# Summary\nExperienced engineer.",
                'format' => 'markdown',
                'markdown' => "# Summary\nExperienced engineer.",
            ],
        ]);

        $fakePath = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        file_put_contents($fakePath, 'fake docx');

        $documentService = $this->createMock(TargetedResumeDocumentService::class);
        $documentService->expects($this->once())
            ->method('ensureDocx')
            ->with($this->callback(fn (TargetedResume $tr) => $tr->is($targetedResume)))
            ->willReturn(['success' => true, 'path' => $fakePath, 'served_cached_document' => false]);
        $this->app->instance(TargetedResumeDocumentService::class, $documentService);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.resume.targeted.download', [$targetedResume, 'docx']));

        $response->assertOk();

        $this->assertDatabaseHas('document_downloads', [
            'targeted_resume_id' => $targetedResume->id,
            'type' => 'docx',
            'served_cached_document' => false,
        ]);
    }

    public function test_regenerate_returns_to_the_list_for_a_resume_with_no_application(): void
    {
        $targetedResume = TargetedResume::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.resume.targeted.regenerate', $targetedResume))
            ->assertRedirect(route('admin.resume.targeted.index'));
    }
}
