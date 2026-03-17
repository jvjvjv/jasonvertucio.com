<?php

namespace App\Models;

use App\Enums\AiInteractionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInteractionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ai_system_id',
        'ai_conversation_id',
        'user_id',
        'feature',
        'input_tokens',
        'output_tokens',
        'model',
        'duration_ms',
        'status',
        'error_message',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'duration_ms' => 'integer',
            'status' => AiInteractionStatus::class,
        ];
    }

    public function aiSystem(): BelongsTo
    {
        return $this->belongsTo(AiSystem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
