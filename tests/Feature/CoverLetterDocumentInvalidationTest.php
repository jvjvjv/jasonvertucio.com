<?php

namespace Tests\Feature;

use App\Models\CoverLetter;
use App\Models\ResumeVersion;
use App\Models\User;
use App\Services\CoverLetterDocumentService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CoverLetterDocumentInvalidationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'cover-letter-invalidation-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
        ]);

        Permission::firstOrCreate(['name' => 'manage-unauthenticated-viewers']);
        $this->admin->givePermissionTo('manage-unauthenticated-viewers');
    }

    public function test_updating_a_cover_letter_invalidates_previously_rendered_documents_without_regenerating(): void
    {
        $version = ResumeVersion::factory()->create(['is_current' => true]);

        $docxPath = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        $pdfPath = tempnam(sys_get_temp_dir(), 'pdf-').'.pdf';
        file_put_contents($docxPath, 'stale');
        file_put_contents($pdfPath, 'stale');

        $coverLetter = CoverLetter::create([
            'resume_version_id' => $version->id,
            'company_name' => 'Acme Corp',
            'position' => 'Senior Software Engineer',
            'date' => now()->toDateString(),
            'greeting' => 'Dear Hiring Manager,',
            'message_body' => 'Original body.',
            'closing' => 'Sincerely,',
            'signature' => 'Jason Vertucio',
            'docx_path' => $docxPath,
            'pdf_path' => $pdfPath,
        ]);

        $documentService = $this->createMock(CoverLetterDocumentService::class);
        $documentService->expects($this->never())->method('generateDocx');
        $documentService->expects($this->never())->method('generatePdf');
        $this->app->instance(CoverLetterDocumentService::class, $documentService);

        $response = $this->actingAs($this->admin)->put(route('admin.cover-letters.update', $coverLetter), [
            'resume_version_id' => $version->id,
            'company_name' => 'Acme Corp',
            'position' => 'Senior Software Engineer',
            'date' => now()->toDateString(),
            'greeting' => 'Dear Hiring Manager,',
            'message_body' => 'Updated body.',
            'closing' => 'Sincerely,',
            'signature' => 'Jason Vertucio',
        ]);

        $response->assertRedirect();

        $this->assertFileDoesNotExist($docxPath);
        $this->assertFileDoesNotExist($pdfPath);

        $coverLetter->refresh();
        $this->assertNull($coverLetter->docx_path);
        $this->assertNull($coverLetter->pdf_path);
    }
}
