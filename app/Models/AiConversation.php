<?php

namespace App\Models;

use App\Enums\AiConversationStatus;
use Illuminate\Database\Eloquent\Builder;
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
        'usage_input_tokens',
        'usage_output_tokens',
        'usage_total_tokens',
        'usage_cost_usd',
        'usage_synced_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'status' => AiConversationStatus::class,
            'last_message_at' => 'datetime',
            'usage_input_tokens' => 'integer',
            'usage_output_tokens' => 'integer',
            'usage_total_tokens' => 'integer',
            'usage_cost_usd' => 'decimal:6',
            'usage_synced_at' => 'datetime',
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

    public function scopeWithLastMessageAt(Builder $query): Builder
    {
        return $query->withMax([
            'messages as last_message_at' => fn (Builder $messageQuery) => $messageQuery->where('role', '!=', 'system'),
        ], 'created_at');
    }

    public function scopeOrderByLastMessageAtDesc(Builder $query): Builder
    {
        return $query
            ->withLastMessageAt()
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at');
    }

    public function getLastMessageAtAttribute(): ?\Illuminate\Support\Carbon
    {
        if (array_key_exists('last_message_at', $this->attributes)) {
            $lastMessageAt = $this->attributes['last_message_at'];

            return $lastMessageAt ? $this->asDateTime($lastMessageAt) : null;
        }

        $lastMessageTimestamp = $this->messages()
            ->reorder()
            ->where('role', '!=', 'system')
            ->latest('created_at')
            ->value('created_at');

        return $lastMessageTimestamp ? $this->asDateTime($lastMessageTimestamp) : null;
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
