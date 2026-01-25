<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResumeShareCode extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The primary key is not auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The primary key type is string.
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'name',
        'email',
        'expires_at',
        'email_sent',
        'notify_on_update',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'expires_at' => 'date',
        'email_sent' => 'boolean',
        'notify_on_update' => 'boolean',
    ];

    /**
     * Get all views for this share code.
     */
    public function views(): HasMany
    {
        return $this->hasMany(ResumeView::class, 'share_code_id');
    }

    /**
     * Scope to get only valid (not expired) codes.
     */
    public function scopeValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>=', now()->toDateString());
        });
    }

    /**
     * Scope to get only codes with email addresses.
     */
    public function scopeWithEmail($query)
    {
        return $query->whereNotNull('email')->where('email', '!=', '');
    }

    /**
     * Scope to get only codes that should receive update notifications.
     */
    public function scopeShouldNotifyOnUpdate($query)
    {
        return $query->withEmail()->where('notify_on_update', true)->valid();
    }

    /**
     * Check if this code is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lt(now()->startOfDay());
    }

    /**
     * Check if this code is valid (not expired and not soft-deleted).
     */
    public function isValid(): bool
    {
        return !$this->trashed() && !$this->isExpired();
    }

    /**
     * Generate a unique 6-character alphanumeric code.
     */
    public static function generateCode(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $length = strlen($chars) - 1;

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, $length)];
            }
        } while (self::withTrashed()->where('id', $code)->exists());

        return $code;
    }

    /**
     * Create a new share code with auto-generated ID.
     */
    public static function generate(
        ?string $expiresAt = null,
        ?string $name = null,
        ?string $email = null,
        bool $notifyOnUpdate = false
    ): self {
        return self::create([
            'id' => self::generateCode(),
            'name' => $name ?? '',
            'email' => $email,
            'expires_at' => $expiresAt,
            'notify_on_update' => $notifyOnUpdate && !empty($email),
        ]);
    }
}
