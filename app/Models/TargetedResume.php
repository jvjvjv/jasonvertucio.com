<?php

namespace App\Models;

use App\Models\Concerns\HasGeneratedDocuments;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A tailored resume document. Everything about the job it was tailored for —
 * company, position, fit, status — lives on its {@see Application}.
 *
 * @property array<string, mixed>|null $tailored_data
 */
class TargetedResume extends Model
{
    use HasFactory;
    use HasGeneratedDocuments;

    protected $fillable = [
        'resume_version_id',
        'title',
        'tailored_data',
        'docx_path',
        'pdf_path',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'tailored_data' => 'array',
        ];
    }

    public function resumeVersion(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class);
    }

    public function application(): HasOne
    {
        return $this->hasOne(Application::class);
    }

    /**
     * Generate the base filename for this targeted resume's documents.
     */
    public function generateFilename(): string
    {
        $this->loadMissing('resumeVersion.personalInfo', 'application.conversation');

        $name = $this->sanitizeFilenamePart($this->resumeVersion?->personalInfo?->name);
        $company = $this->sanitizeFilenamePart($this->application?->company_name);
        $suffix = $this->application?->documentFilenameSuffix() ?? "resume-{$this->id}";

        return trim("{$name} Resume {$company} {$suffix}");
    }

    private function sanitizeFilenamePart(?string $value): string
    {
        $sanitized = preg_replace('~[\\/:*?"<>|]+~', '-', (string) $value) ?? '';
        $sanitized = preg_replace('/\s+/', ' ', $sanitized) ?? $sanitized;
        $sanitized = trim($sanitized, " .-\t\n\r\0\x0B");

        return $sanitized !== '' ? $sanitized : 'Unknown';
    }
}
