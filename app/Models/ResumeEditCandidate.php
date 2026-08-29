<?php

namespace App\Models;

use Database\Factories\ResumeEditCandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jvjvjv\CodeTalker\Models\AiConversation;

class ResumeEditCandidate extends Model
{
    /** @use HasFactory<ResumeEditCandidateFactory> */
    use HasFactory;

    protected $fillable = [
        'base_resume_version_id',
        'revision_number',
        'status',
        'snapshot',
        'ai_conversation_id',
        'batch_started_at',
        'last_edited_at',
        'approved_at',
        'approved_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'batch_started_at' => 'datetime',
            'last_edited_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function baseResumeVersion(): BelongsTo
    {
        return $this->belongsTo(ResumeVersion::class, 'base_resume_version_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
