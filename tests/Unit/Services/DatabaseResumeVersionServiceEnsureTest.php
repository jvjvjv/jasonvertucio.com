<?php

namespace Tests\Unit\Services;

use App\Contracts\ResumeDataServiceContract;
use App\Models\ResumeVersion;
use App\Services\DatabaseResumeVersionService;
use App\Services\Resume\DocumentRenderer;
use App\Services\Resume\HtmlDocumentComposer;
use App\Services\Resume\MarkdownToHtmlConverter;
use App\Services\Resume\MarkdownToOpenXmlConverter;
use App\Services\Resume\PdfRenderer;
use App\Services\Resume\ResumeMarkdownComposer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DatabaseResumeVersionServiceEnsureTest extends TestCase
{
    use DatabaseTransactions;

    private function service(): DatabaseResumeVersionService
    {
        return $this->getMockBuilder(DatabaseResumeVersionService::class)
            ->setConstructorArgs([
                $this->createStub(ResumeDataServiceContract::class),
                new DocumentRenderer,
                app(ResumeMarkdownComposer::class),
                new MarkdownToOpenXmlConverter,
                new MarkdownToHtmlConverter,
                app(HtmlDocumentComposer::class),
                new PdfRenderer,
            ])
            ->onlyMethods(['generateDocx', 'generatePdf'])
            ->getMock();
    }

    public function test_ensure_docx_short_circuits_when_the_current_version_has_a_currently_valid_file(): void
    {
        config(['resume.document_retention_mode' => 'cache', 'resume.document_retention_hours' => 12]);

        $path = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        file_put_contents($path, 'content');

        ResumeVersion::factory()->create(['is_current' => true, 'docx_path' => $path]);

        $service = $this->service();
        $service->expects($this->never())->method('generateDocx');

        $result = $service->ensureDocx();

        $this->assertTrue($result['success']);
        $this->assertTrue($result['served_cached_document']);
        $this->assertSame($path, $result['path']);
    }

    public function test_ensure_docx_generates_when_the_current_version_has_no_file(): void
    {
        ResumeVersion::factory()->create(['is_current' => true, 'docx_path' => null]);

        $service = $this->service();
        $service->expects($this->once())->method('generateDocx')->willReturn(['success' => true, 'path' => '/new.docx']);

        $result = $service->ensureDocx();

        $this->assertTrue($result['success']);
        $this->assertFalse($result['served_cached_document']);
    }

    public function test_ensure_pdf_propagates_a_generation_failure(): void
    {
        ResumeVersion::factory()->create(['is_current' => true, 'pdf_path' => null]);

        $service = $this->service();
        $service->method('generatePdf')->willReturn(['success' => false, 'error' => 'boom']);

        $result = $service->ensurePdf();

        $this->assertFalse($result['success']);
        $this->assertSame('boom', $result['error']);
    }
}
