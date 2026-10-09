<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jvjvjv\CodeTalker\Models\AiConversation as BaseAiConversation;

/**
 * A tracked job: the job details, the fit assessment, the status and the
 * resume used for it. Whether a targeted resume was built, and whether an AI
 * session exists, are both optional.
 *
 * @property ApplicationStatus $status
 */
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Stored when a job is recorded before its company or position is known,
     * because both columns are required.
     */
    public const string UNKNOWN_COMPANY = 'Unknown Company';

    public const string UNKNOWN_POSITION = 'Unknown Position';

    protected $fillable = [
        'resume_version_id',
        'targeted_resume_id',
        'ai_conversation_id',
        'job_url_id',
        'company_name',
        'position',
        'location',
        'job_description',
        'fit_score',
        'fit_summary',
        'status',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'fit_score' => 'integer',
        ];
    }

    /**
     * The application an AI session belongs to. Looked up by key rather than
     * through a relation because services and tools are handed the package's
     * conversation model, which does not carry the host's relations.
     */
    public static function forConversation(BaseAiConversation $conversation): ?self
    {
        if ($conversation->getKey() === null) {
            return null;
        }

        return self::query()->where('ai_conversation_id', $conversation->getKey())->first();
    }

    public function resumeVersion(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class);
    }

    public function targetedResume(): BelongsTo
    {
        return $this->belongsTo(TargetedResume::class);
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
        return $this->hasMany(ApplicationStatusUpdate::class)->orderBy('occurred_at');
    }

    public function latestStatusUpdate(): HasOne
    {
        return $this->hasOne(ApplicationStatusUpdate::class)->latestOfMany('occurred_at');
    }

    /**
     * Whether the application has ever been applied to. From then on its
     * targeted resume, if any, is the record of what was sent.
     */
    public function hasBeenApplied(): bool
    {
        return $this->statusUpdates()->where('status', ApplicationStatus::Applied->value)->exists();
    }

    /**
     * The company name, or null while it is still the placeholder.
     */
    public function knownCompanyName(): ?string
    {
        return $this->company_name !== self::UNKNOWN_COMPANY ? $this->company_name : null;
    }

    /**
     * The position, or null while it is still the placeholder.
     */
    public function knownPosition(): ?string
    {
        return $this->position !== self::UNKNOWN_POSITION ? $this->position : null;
    }

    /**
     * The trailing part of a generated document's filename that tells this
     * application's documents apart from another's for the same company.
     */
    public function documentFilenameSuffix(): string
    {
        return $this->conversation?->uuid ?? "app-{$this->id}";
    }
}
