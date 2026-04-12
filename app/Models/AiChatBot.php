<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiChatBot extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const ACCESS_PATH_CHAT = 'chat';

    public const ACCESS_PATH_ROOT = 'root';

    protected $fillable = [
        'ai_system_id',
        'name',
        'slug',
        'access_path',
        'description',
        'prompt_template',
        'allowed_roles',
        'is_active',
        'is_public',
        'require_visitor_identity',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'allowed_roles' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'require_visitor_identity' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return array<int, string>
     */
    public static function reservedRootSlugs(): array
    {
        return [
            '_boost',
            'about',
            'admin',
            'api',
            'blog',
            'canvas',
            'chat',
            'forgot-password',
            'login',
            'logout',
            'mlopnadjs22tn',
            'paper',
            'passkey',
            'profile',
            'register',
            'reset-password',
            'resume',
            'sanctum',
            'two-factor-challenge',
            'user',
            'wp-admin',
            'wp-login.php',
        ];
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function aiSystem(): BelongsTo
    {
        return $this->belongsTo(AiSystem::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(AiConversation::class);
    }

    public function interactionLogs(): HasMany
    {
        return $this->hasMany(AiInteractionLog::class);
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

    public function featureKey(): string
    {
        return 'chat-bot:' . $this->slug;
    }

    public function usesRootAccessPath(): bool
    {
        return $this->access_path === self::ACCESS_PATH_ROOT;
    }

    public function usesChatAccessPath(): bool
    {
        return $this->access_path !== self::ACCESS_PATH_ROOT;
    }

    public function publicPath(): string
    {
        return $this->usesRootAccessPath()
            ? '/' . $this->slug
            : '/chat/' . $this->slug;
    }
}
