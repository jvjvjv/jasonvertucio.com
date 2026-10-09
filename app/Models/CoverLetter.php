<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedDocuments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoverLetter extends Model
{
    use HasFactory;
    use HasGeneratedDocuments;

    /**
     * Filename suffix of a letter that belongs to no application.
     */
    private const string STANDALONE_FILENAME_SUFFIX = 'unknown';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'resume_version_id',
        'application_id',
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
     * The job this letter was written for. Null for a standalone letter
     * created from the Cover Letters page with no application chosen.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Generate the base filename for this cover letter's documents.
     */
    public function generateFilename(): string
    {
        $this->loadMissing('resumeVersion.personalInfo', 'application.conversation');

        $name = $this->sanitizeFilenamePart($this->resumeVersion?->personalInfo?->name);
        $company = $this->sanitizeFilenamePart($this->company_name);
        $suffix = $this->application?->documentFilenameSuffix() ?? self::STANDALONE_FILENAME_SUFFIX;
        $date = $this->date->format('Y-m-d');

        return trim("{$name} Cover Letter {$company} {$date} {$suffix}");
    }

    private function sanitizeFilenamePart(?string $value): string
    {
        $sanitized = preg_replace('~[\\/:*?"<>|]+~', '-', (string) $value) ?? '';
        $sanitized = preg_replace('/\s+/', ' ', $sanitized) ?? $sanitized;
        $sanitized = trim($sanitized, " .-\t\n\r\0\x0B");

        return $sanitized !== '' ? $sanitized : 'Unknown';
    }
}
