<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Jvjvjv\CodeTalker\Models\AiChatBot as BaseAiChatBot;

class AiChatBot extends BaseAiChatBot
{
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
            'chats',
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
