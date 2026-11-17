<?php

namespace App\Models;

use App\Enums\FollowUpDiscardReason;
use App\Enums\FollowUpStatus;
use Database\Factories\ApplicationFollowUpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * An AI-drafted follow-up email to an application's contacts. `recipients`
 * is the snapshot of contact addresses taken when it was approved, so
 * removing a contact later does not change what was sent.
 *
 * @property FollowUpStatus $status
 * @property ?FollowUpDiscardReason $discard_reason
 * @property ?list<string> $recipients
 */
class ApplicationFollowUp extends Model
{
    /** @use HasFactory<ApplicationFollowUpFactory> */
    use HasFactory;

    protected $fillable = [
        'application_id',
        'status',
        'subject',
        'body',
        'recipients',
        'ai_system_id',
        'drafted_at',
        'sent_at',
        'discarded_at',
        'discard_reason',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => FollowUpStatus::class,
            'discard_reason' => FollowUpDiscardReason::class,
            'recipients' => 'array',
            'drafted_at' => 'datetime',
            'sent_at' => 'datetime',
            'discarded_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function aiSystem(): BelongsTo
    {
        return $this->belongsTo(AiSystem::class);
    }
}
