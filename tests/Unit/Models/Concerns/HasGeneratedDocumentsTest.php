<?php

namespace Tests\Unit\Models\Concerns;

use App\Models\TargetedResume;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HasGeneratedDocumentsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_invalidate_docx_deletes_the_file_and_nulls_the_column(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        file_put_contents($path, 'content');

        $targetedResume = TargetedResume::factory()->create(['docx_path' => $path]);

        $targetedResume->invalidateDocx();

        $this->assertFileDoesNotExist($path);
        $this->assertNull($targetedResume->fresh()->docx_path);
    }

    public function test_invalidate_docx_is_a_no_op_when_already_null(): void
    {
        $targetedResume = TargetedResume::factory()->create(['docx_path' => null]);

        $targetedResume->invalidateDocx();

        $this->assertNull($targetedResume->fresh()->docx_path);
    }

    public function test_invalidate_documents_invalidates_both_formats(): void
    {
        $docxPath = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        $pdfPath = tempnam(sys_get_temp_dir(), 'pdf-').'.pdf';
        file_put_contents($docxPath, 'content');
        file_put_contents($pdfPath, 'content');

        $targetedResume = TargetedResume::factory()->create([
            'docx_path' => $docxPath,
            'pdf_path' => $pdfPath,
        ]);

        $targetedResume->invalidateDocuments();

        $this->assertFileDoesNotExist($docxPath);
        $this->assertFileDoesNotExist($pdfPath);
        $fresh = $targetedResume->fresh();
        $this->assertNull($fresh->docx_path);
        $this->assertNull($fresh->pdf_path);
    }

    public function test_docx_exists_reflects_the_file_on_disk(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'docx-').'.docx';
        file_put_contents($path, 'content');

        $targetedResume = TargetedResume::factory()->create(['docx_path' => $path]);

        $this->assertTrue($targetedResume->docxExists());

        unlink($path);

        $this->assertFalse($targetedResume->docxExists());
    }
}
