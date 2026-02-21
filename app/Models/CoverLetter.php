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

    /**
     * Generate the base filename for this cover letter's documents.
     */
    public function generateFilename(): string
    {
        return "{$this->company_name} {$this->position} {$this->date->format('Y-m-d')}";
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
