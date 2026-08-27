<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jvjvjv\CodeTalker\Models\AiConversation as BaseAiConversation;
use Jvjvjv\CodeTalker\Models\AiInteractionLog;
use Jvjvjv\CodeTalker\Models\AiPersona as BaseAiPersona;

class AiChatBot extends BaseAiPersona
{
    // The base AiPersona model has no explicit $table — it relies on Eloquent's
    // convention, which derives the name from the leaf class. Keeping this
    // class named AiChatBot (rather than renaming to match the package) means
    // that convention would resolve to the now-nonexistent `ai_chat_bots`.
    protected $table = 'ai_personas';

    protected $fillable = [
        'ai_system_id',
        'context_length',
        'temperature',
        'name',
        'slug',
        'access_path',
        'description',
        'prompt_template',
        'allowed_roles',
        'is_active',
        'require_visitor_identity',
        'tools_enabled',
    ];

    protected $casts = [
        'allowed_roles' => 'array',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'allowed_roles' => 'array',
            'context_length' => 'integer',
            'temperature' => 'decimal:2',
            'is_active' => 'boolean',
            'require_visitor_identity' => 'boolean',
            'tools_enabled' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The base AiPersona::conversations()/interactionLogs() don't pass an
     * explicit foreign key, so Eloquent infers one from the owning model's
     * leaf class — `ai_chat_bot_id` here, not the actual `ai_persona_id`
     * column, for the same reason $table above needs to be explicit.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(BaseAiConversation::class, 'ai_persona_id');
    }

    public function interactionLogs(): HasMany
    {
        return $this->hasMany(AiInteractionLog::class, 'ai_persona_id');
    }

    public function allowsRole(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $allowedRoles = $this->allowed_roles ?? [];

        if ($allowedRoles === []) {
            return true;
        }

        return $user->hasAnyRole($allowedRoles);
    }
}
