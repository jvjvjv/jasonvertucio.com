<?php

namespace App\Models;

use BSPDX\Keystone\Contracts\HasPasskeys;
use BSPDX\Keystone\Traits\HasKeystone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable implements HasPasskeys
{
    use HasFactory, HasKeystone, HasUuids, Notifiable;

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
}
