<?php

namespace App\Models;

use App\Enums\TargetedResumeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property array<string, mixed>|null $tailored_data
 */
class TargetedResume extends Model
{
    use HasFactory;

    protected $fillable = [
        'resume_version_id',
        'ai_conversation_id',
        'job_url_id',
        'company_name',
        'position',
        'title',
        'job_description',
        'tailored_data',
        'fit_score',
        'fit_summary',
        'docx_path',
        'pdf_path',
        'base_resume',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'tailored_data' => 'array',
            'fit_score' => 'integer',
            'status' => TargetedResumeStatus::class,
            'base_resume' => 'boolean',
        ];
    }

    public function resumeVersion(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function jobUrl(): BelongsTo
    {
        return $this->belongsTo(JobUrl::class);
    }

    public function coverLetters(): HasMany
    {
        return $this->hasMany(CoverLetter::class);
    }

    public function statusUpdates(): HasMany
    {
        return $this->hasMany(TargetedResumeStatusUpdate::class)->orderBy('occurred_at');
    }

    public function latestStatusUpdate(): HasOne
    {
        return $this->hasOne(TargetedResumeStatusUpdate::class)->latestOfMany('occurred_at');
    }

    /**
     * Generate the base filename for this targeted resume's documents.
     */
    public function generateFilename(): string
    {
        $this->loadMissing('resumeVersion.personalInfo', 'conversation');

        $name = $this->sanitizeFilenamePart($this->resumeVersion?->personalInfo?->name);
        $company = $this->sanitizeFilenamePart($this->company_name);
        $uuid = $this->conversation?->uuid ?? 'unknown';

        return trim("{$name} Resume {$company} {$uuid}");
    }

    private function sanitizeFilenamePart(?string $value): string
    {
        $sanitized = preg_replace('~[\\/:*?"<>|]+~', '-', (string) $value) ?? '';
        $sanitized = preg_replace('/\s+/', ' ', $sanitized) ?? $sanitized;
        $sanitized = trim($sanitized, " .-\t\n\r\0\x0B");

        return $sanitized !== '' ? $sanitized : 'Unknown';
    }

    /**
     * Check if a DOCX file exists for this targeted resume.
     */
    public function docxExists(): bool
    {
        return $this->docx_path !== null && file_exists($this->docx_path);
    }

    /**
     * Check if a PDF file exists for this targeted resume.
     */
    public function pdfExists(): bool
    {
        return $this->pdf_path !== null && file_exists($this->pdf_path);
    }
}
