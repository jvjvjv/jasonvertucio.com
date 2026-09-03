<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jvjvjv\CodeTalker\Models\AiConversation as BaseAiConversation;
use Jvjvjv\CodeTalker\Models\AiInteractionLog;
use Jvjvjv\CodeTalker\Models\AiPersona as BaseAiPersona;

class AiChatBot extends BaseAiPersona
{
    /**
     * A `required_permission` value of "authenticated" is not a Keystone
     * permission — it's the same special "any signed-in user" bucket
     * `SiteSettingsController` uses for nav links, reused here so a persona
     * can require login without being tied to a specific permission.
     */
    public const PERMISSION_AUTHENTICATED = 'authenticated';


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
        'required_permission',
        'is_active',
        'require_visitor_identity',
        'tools_enabled',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
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

    public function allowsAccess(?User $user): bool
    {
        if ($this->required_permission === null) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        if ($this->required_permission === self::PERMISSION_AUTHENTICATED) {
            return true;
        }

        return $user->can($this->required_permission);
    }
}
