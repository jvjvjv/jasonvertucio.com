<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Logs a download of any generated document — the main resume, a targeted
 * resume, or a cover letter — regardless of format.
 *
 * Distinct from `ResumeDownload`, which tracks only main-resume downloads and
 * carries share-code-specific fields (`share_code_id`). Exactly one of
 * `resume_id`, `targeted_resume_id`, and `cover_letter_id` is set per row,
 * identifying which document was downloaded; `type` records the file
 * format, not the document kind.
 */
class DocumentDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'resume_id',
        'targeted_resume_id',
        'cover_letter_id',
        'type',
        'served_cached_document',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'served_cached_document' => 'boolean',
        ];
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class, 'resume_id');
    }

    public function targetedResume(): BelongsTo
    {
        return $this->belongsTo(TargetedResume::class);
    }

    public function coverLetter(): BelongsTo
    {
        return $this->belongsTo(CoverLetter::class);
    }

    /**
     * Whichever of resume/targetedResume/coverLetter is set for this row.
     */
    protected function document(): Attribute
    {
        return Attribute::make(
            get: fn (): ResumeVersion|TargetedResume|CoverLetter|null => $this->resume ?? $this->targetedResume ?? $this->coverLetter,
        );
    }
}
