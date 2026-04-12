<?php

namespace App\Models;

use App\Enums\AiConversationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AiConversation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'user_id',
        'ai_system_id',
        'ai_chat_bot_id',
        'feature',
        'title',
        'visitor_name',
        'visitor_email',
        'status',
        'context',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'status' => AiConversationStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $conversation): void {
            if (blank($conversation->public_id)) {
                $conversation->public_id = (string) Str::ulid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiSystem(): BelongsTo
    {
        return $this->belongsTo(AiSystem::class);
    }

    public function aiChatBot(): BelongsTo
    {
        return $this->belongsTo(AiChatBot::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiConversationMessage::class)->orderBy('created_at');
    }

    public function interactionLogs(): HasMany
    {
        return $this->hasMany(AiInteractionLog::class);
    }

    public function targetedResume(): HasOne
    {
        return $this->hasOne(TargetedResume::class);
    }
}
