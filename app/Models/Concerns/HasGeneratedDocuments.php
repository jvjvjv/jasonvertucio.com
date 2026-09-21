<?php

namespace App\Models\Concerns;

/**
 * Existence and invalidation for a model's generated `docx_path`/`pdf_path`
 * files, shared by every model whose documents are rendered on demand
 * (`ResumeVersion`, `TargetedResume`, `CoverLetter`).
 */
trait HasGeneratedDocuments
{
    public function docxExists(): bool
    {
        return $this->docx_path !== null && file_exists($this->docx_path);
    }

    public function pdfExists(): bool
    {
        return $this->pdf_path !== null && file_exists($this->pdf_path);
    }

    public function invalidateDocx(): void
    {
        if ($this->docx_path !== null && file_exists($this->docx_path)) {
            @unlink($this->docx_path);
        }

        $this->forceFill(['docx_path' => null])->save();
    }

    public function invalidatePdf(): void
    {
        if ($this->pdf_path !== null && file_exists($this->pdf_path)) {
            @unlink($this->pdf_path);
        }

        $this->forceFill(['pdf_path' => null])->save();
    }

    public function invalidateDocuments(): void
    {
        $this->invalidateDocx();
        $this->invalidatePdf();
    }
}
