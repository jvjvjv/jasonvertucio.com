<?php

namespace Tests\Feature;

use App\Models\CoverLetter;
use App\Models\User;
use App\Services\CoverLetterDocumentService;
use BSPDX\Keystone\Models\KeystonePermission as Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CoverLetterDownloadOnDemandTest extends TestCase
{
    use DatabaseTransactions;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'cover-letter-download-'.uniqid().'@test.com',
            'password' => bcrypt('password'),
        ]);

        Permission::firstOrCreate(['name' => 'manage-unauthenticated-viewers']);
        $this->admin->givePermissionTo('manage-unauthenticated-viewers');
    }

    private function makeCoverLetter(): CoverLetter
    {
        return CoverLetter::create([
            'company_name' => 'Acme Corp',
            'position' => 'Senior Software Engineer',
            'date' => now()->toDateString(),
            'greeting' => 'Dear Hiring Manager,',
            'message_body' => 'Body.',
            'closing' => 'Sincerely,',
            'signature' => 'Jason Vertucio',
        ]);
    }

    public function test_downloading_docx_generates_it_when_missing_and_logs_the_download(): void
    {
        $coverLetter = $this->makeCoverLetter();

        $fakePath = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        file_put_contents($fakePath, 'fake docx');

        $documentService = $this->createMock(CoverLetterDocumentService::class);
        $documentService->method('ensureDocx')->willReturn([
            'success' => true,
            'path' => $fakePath,
            'served_cached_document' => false,
        ]);
        $this->app->instance(CoverLetterDocumentService::class, $documentService);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cover-letters.download.docx', $coverLetter));

        $response->assertOk();

        $this->assertDatabaseHas('document_downloads', [
            'cover_letter_id' => $coverLetter->id,
            'type' => 'docx',
            'served_cached_document' => false,
        ]);
    }

    public function test_downloading_pdf_reports_the_generation_error_instead_of_redirecting_to_regenerate(): void
    {
        $coverLetter = $this->makeCoverLetter();

        $documentService = $this->createMock(CoverLetterDocumentService::class);
        $documentService->method('ensurePdf')->willReturn([
            'success' => false,
            'error' => 'WeasyPrint failed.',
        ]);
        $this->app->instance(CoverLetterDocumentService::class, $documentService);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.cover-letters.download.pdf', $coverLetter));

        $response->assertRedirect(route('admin.cover-letters.edit', $coverLetter));
        $response->assertSessionHas('error');
    }
}
