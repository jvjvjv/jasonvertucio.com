<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiSystem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'provider',
        'api_key',
        'model',
        'base_url',
        'api_version',
        'max_tokens',
        'temperature',
        'is_active',
        'config',
        'system_prompt',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'config' => 'array',
            'is_active' => 'boolean',
            'temperature' => 'decimal:2',
            'max_tokens' => 'integer',
        ];
    }

    /**
     * Scope to only active systems.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the default AI system for a given feature.
     */
    public static function defaultForFeature(string $feature): ?self
    {
        $default = AiSystemFeatureDefault::where('feature', $feature)->first();

        return $default?->aiSystem;
    }

    public function featureDefaults(): HasMany
    {
        return $this->hasMany(AiSystemFeatureDefault::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function chatBots(): HasMany {
        return $this->hasMany(AiChatBot::class);
    }

    public function interactionLogs(): HasMany
    {
        return $this->hasMany(AiInteractionLog::class);
    }
}
