<?php

namespace Tests\Unit\Services;

use App\Models\TargetedResume;
use App\Services\Resume\DocumentRenderer;
use App\Services\Resume\HtmlDocumentComposer;
use App\Services\Resume\MarkdownToHtmlConverter;
use App\Services\Resume\MarkdownToOpenXmlConverter;
use App\Services\Resume\PdfRenderer;
use App\Services\TargetedResumeDocumentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TargetedResumeDocumentServiceEnsureTest extends TestCase
{
    use DatabaseTransactions;

    private function service(): TargetedResumeDocumentService
    {
        return $this->getMockBuilder(TargetedResumeDocumentService::class)
            ->setConstructorArgs([
                new MarkdownToOpenXmlConverter,
                new DocumentRenderer,
                new MarkdownToHtmlConverter,
                new HtmlDocumentComposer,
                new PdfRenderer,
            ])
            ->onlyMethods(['generateDocx', 'generatePdf'])
            ->getMock();
    }

    public function test_ensure_docx_short_circuits_when_a_currently_valid_file_exists(): void
    {
        config(['resume.document_retention_mode' => 'cache', 'resume.document_retention_hours' => 12]);

        $path = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        file_put_contents($path, 'content');

        $targetedResume = TargetedResume::factory()->create(['docx_path' => $path]);

        $service = $this->service();
        $service->expects($this->never())->method('generateDocx');

        $result = $service->ensureDocx($targetedResume);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['served_cached_document']);
        $this->assertSame($path, $result['path']);
    }

    public function test_ensure_docx_regenerates_when_the_cached_file_has_expired(): void
    {
        config(['resume.document_retention_mode' => 'cache', 'resume.document_retention_hours' => 12]);

        $path = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        file_put_contents($path, 'content');
        touch($path, now()->subHours(13)->timestamp);

        $targetedResume = TargetedResume::factory()->create(['docx_path' => $path]);

        $service = $this->service();
        $service->expects($this->once())
            ->method('generateDocx')
            ->willReturn(['success' => true, 'path' => '/new/path.docx']);

        $result = $service->ensureDocx($targetedResume);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['served_cached_document']);
    }

    public function test_ensure_docx_regenerates_when_no_file_exists(): void
    {
        $targetedResume = TargetedResume::factory()->create(['docx_path' => null]);

        $service = $this->service();
        $service->expects($this->once())
            ->method('generateDocx')
            ->willReturn(['success' => true, 'path' => '/new/path.docx']);

        $result = $service->ensureDocx($targetedResume);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['served_cached_document']);
    }

    public function test_ensure_docx_propagates_a_generation_failure(): void
    {
        $targetedResume = TargetedResume::factory()->create(['docx_path' => null]);

        $service = $this->service();
        $service->method('generateDocx')->willReturn(['success' => false, 'error' => 'boom']);

        $result = $service->ensureDocx($targetedResume);

        $this->assertFalse($result['success']);
        $this->assertSame('boom', $result['error']);
    }

    public function test_ensure_docx_in_delete_after_serve_mode_treats_any_existing_file_as_valid(): void
    {
        config(['resume.document_retention_mode' => 'delete_after_serve']);

        $path = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        file_put_contents($path, 'content');
        touch($path, now()->subDays(30)->timestamp);

        $targetedResume = TargetedResume::factory()->create(['docx_path' => $path]);

        $service = $this->service();
        $service->expects($this->never())->method('generateDocx');

        $result = $service->ensureDocx($targetedResume);

        $this->assertTrue($result['served_cached_document']);
    }
}
