<?php

namespace App\Models;

use BSPDX\Keystone\Traits\HasKeystone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\LaravelPasskeys\Models\Concerns\HasPasskeys;
use Spatie\LaravelPasskeys\Models\Concerns\InteractsWithPasskeys;

class User extends Authenticatable implements HasPasskeys
{
    use HasApiTokens, HasFactory, HasKeystone, HasUuids, InteractsWithPasskeys, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
        'allow_passkey_login',
        'allow_totp_login',
        'require_password',
        'show_tool_payloads',
        'username',
        'summary',
        'avatar',
        'dark_mode',
        'digest',
        'locale',
        'role',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_confirmed_at' => 'datetime',
        'allow_passkey_login' => 'boolean',
        'allow_totp_login' => 'boolean',
        'require_password' => 'boolean',
        'show_tool_payloads' => 'boolean',
        'dark_mode' => 'boolean',
        'digest' => 'boolean',
        'role' => 'integer',
    ];

    /**
     * Get the names of the roles assigned to the user.
     *
     * @return Collection<int, string>
     */
    public function getRoleNames(): Collection
    {
        return $this->roles->pluck('name');
    }

    /**
     * Canvas compatibility: Check if user is an Admin.
     */
    public function getIsAdminAttribute(): bool
    {
        return $this->hasAnyRole(['super-admin', 'admin']);
    }

    /**
     * Canvas compatibility: Check if user is an Editor.
     */
    public function getIsEditorAttribute(): bool
    {
        return $this->hasAnyRole(['super-admin', 'admin', 'editor']);
    }

    /**
     * Canvas compatibility: Check if user is a Contributor.
     */
    public function getIsContributorAttribute(): bool
    {
        return $this->hasAnyRole(['super-admin', 'admin', 'editor', 'contributor']);
    }

    /**
     * Canvas compatibility: Return a default avatar.
     */
    public function getDefaultAvatarAttribute(): string
    {
        return 'https://www.gravatar.com/avatar/'.md5(strtolower(trim($this->email ?? ''))).'?d=mp';
    }

    /**
     * Canvas compatibility: Return a default locale.
     */
    public function getDefaultLocaleAttribute(): string
    {
        return config('app.locale');
    }

    /**
     * Determine if the user has enabled two-factor authentication.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_secret) &&
               ! is_null($this->two_factor_confirmed_at);
    }

    /**
     * Determine if the user has registered any passkeys.
     */
    public function hasPasskeysRegistered(): bool
    {
        return $this->passkeys()->exists();
    }

    /**
     * Check if user can use passwordless login.
     */
    public function canUsePasswordlessLogin(): bool
    {
        return ($this->allow_passkey_login && $this->hasPasskeysRegistered()) ||
               ($this->allow_totp_login && $this->hasTwoFactorEnabled());
    }

    /**
     * Get available authentication methods for this user.
     */
    public function getAvailableAuthMethods(): array
    {
        $methods = [];

        if ($this->require_password) {
            $methods[] = 'password';
        }

        if ($this->allow_passkey_login && $this->hasPasskeysRegistered()) {
            $methods[] = 'passkey';
        }

        if ($this->allow_totp_login && $this->hasTwoFactorEnabled()) {
            $methods[] = 'totp';
        }

        return $methods;
    }

    /**
     * Validate that at least one auth method is enabled.
     */
    public function hasValidAuthConfiguration(): bool
    {
        return $this->require_password ||
               ($this->allow_passkey_login && $this->hasPasskeysRegistered()) ||
               ($this->allow_totp_login && $this->hasTwoFactorEnabled());
    }
}
