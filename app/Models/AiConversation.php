<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AiConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ai_system_id',
        'feature',
        'title',
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
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiSystem(): BelongsTo
    {
        return $this->belongsTo(AiSystem::class);
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
