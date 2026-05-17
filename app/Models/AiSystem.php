<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiSystem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'provider',
        'api_key',
        'model',
        'base_url',
        'api_version',
        'max_tokens',
        'context_length',
        'temperature',
        'is_active',
        'config',
        'system_prompt',
        'credentials',
        'auth_type',
        'endpoint_type',
        'stream_protocol',
        'system_prompt_mode',
        'supports_tools',
        'allowed_tools',
        'supports_json_mode',
        'is_local_endpoint',
        'pricing_profile',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'config' => 'array',
            'credentials' => 'encrypted:array',
            'pricing_profile' => 'array',
            'is_active' => 'boolean',
            'supports_tools' => 'boolean',
            'allowed_tools' => 'array',
            'supports_json_mode' => 'boolean',
            'is_local_endpoint' => 'boolean',
            'temperature' => 'decimal:2',
            'max_tokens' => 'integer',
            'context_length' => 'integer',
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
