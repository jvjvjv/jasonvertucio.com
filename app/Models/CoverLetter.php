<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoverLetter extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'resume_version_id',
        'targeted_resume_id',
        'company_name',
        'position',
        'date',
        'company_address',
        'greeting',
        'message_body',
        'closing',
        'signature',
        'docx_path',
        'pdf_path',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function resumeVersion(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class, 'resume_version_id');
    }

    public function targetedResume(): BelongsTo
    {
        return $this->belongsTo(TargetedResume::class);
    }

    /**
     * Generate the base filename for this cover letter's documents.
     */
    public function generateFilename(): string
    {
        $companyName = $this->sanitizeFilenamePart($this->company_name);
        $position = $this->sanitizeFilenamePart($this->position);

        return trim("{$companyName} {$position} {$this->date->format('Y-m-d')}");
    }

    private function sanitizeFilenamePart(?string $value): string
    {
        $sanitized = preg_replace('~[\\/:*?"<>|]+~', '-', (string) $value) ?? '';
        $sanitized = preg_replace('/\s+/', ' ', $sanitized) ?? $sanitized;
        $sanitized = trim($sanitized, " .-\t\n\r\0\x0B");

        return $sanitized !== '' ? $sanitized : 'Unknown';
    }

    /**
     * Check if a DOCX file exists for this cover letter.
     */
    public function docxExists(): bool
    {
        return $this->docx_path !== null && file_exists($this->docx_path);
    }

    /**
     * Check if a PDF file exists for this cover letter.
     */
    public function pdfExists(): bool
    {
        return $this->pdf_path !== null && file_exists($this->pdf_path);
    }
}
