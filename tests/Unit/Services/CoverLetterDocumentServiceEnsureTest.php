<?php

namespace Tests\Unit\Services;

use App\Models\CoverLetter;
use App\Services\CoverLetterDocumentService;
use App\Services\Resume\CoverLetterBodyComposer;
use App\Services\Resume\CoverLetterHtmlComposer;
use App\Services\Resume\DocumentRenderer;
use App\Services\Resume\HtmlDocumentComposer;
use App\Services\Resume\PdfRenderer;
use App\Services\Resume\SignatureImageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CoverLetterDocumentServiceEnsureTest extends TestCase
{
    use DatabaseTransactions;

    private function service(): CoverLetterDocumentService
    {
        return $this->getMockBuilder(CoverLetterDocumentService::class)
            ->setConstructorArgs([
                app(CoverLetterBodyComposer::class),
                new DocumentRenderer,
                app(SignatureImageService::class),
                app(CoverLetterHtmlComposer::class),
                app(HtmlDocumentComposer::class),
                new PdfRenderer,
            ])
            ->onlyMethods(['generateDocx', 'generatePdf'])
            ->getMock();
    }

    private function coverLetter(array $overrides = []): CoverLetter
    {
        return CoverLetter::create(array_merge([
            'company_name' => 'Acme',
            'position' => 'Engineer',
            'date' => now()->toDateString(),
            'greeting' => 'Dear Hiring Manager,',
            'message_body' => 'Body.',
        ], $overrides));
    }

    public function test_ensure_pdf_short_circuits_when_a_currently_valid_file_exists(): void
    {
        config(['resume.document_retention_mode' => 'cache', 'resume.document_retention_hours' => 12]);

        $path = tempnam(sys_get_temp_dir(), 'pdf-').'.pdf';
        file_put_contents($path, 'content');

        $coverLetter = $this->coverLetter(['pdf_path' => $path]);

        $service = $this->service();
        $service->expects($this->never())->method('generatePdf');

        $result = $service->ensurePdf($coverLetter);

        $this->assertTrue($result['served_cached_document']);
    }

    public function test_ensure_pdf_regenerates_when_missing(): void
    {
        $coverLetter = $this->coverLetter(['pdf_path' => null]);

        $service = $this->service();
        $service->expects($this->once())->method('generatePdf')->willReturn(['success' => true, 'path' => '/new.pdf']);

        $result = $service->ensurePdf($coverLetter);

        $this->assertFalse($result['served_cached_document']);
        $this->assertTrue($result['success']);
    }
}
