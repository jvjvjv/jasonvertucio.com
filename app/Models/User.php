<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use BSPDX\Keystone\Traits\HasKeystone;
use BSPDX\Keystone\Contracts\HasPasskeys;

class User extends Authenticatable implements HasPasskeys
{
    use HasFactory, HasUuids, Notifiable, HasKeystone;

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
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getRoleNames(): \Illuminate\Support\Collection
    {
        return $this->roles->pluck('name');
    }

    /**
     * Canvas compatibility: Check if user is an Admin.
     *
     * @return bool
     */
    public function getIsAdminAttribute(): bool
    {
        return $this->hasAnyRole(['super-admin', 'admin']);
    }

    /**
     * Canvas compatibility: Check if user is an Editor.
     *
     * @return bool
     */
    public function getIsEditorAttribute(): bool
    {
        return $this->hasAnyRole(['super-admin', 'admin', 'editor']);
    }

    /**
     * Canvas compatibility: Check if user is a Contributor.
     *
     * @return bool
     */
    public function getIsContributorAttribute(): bool
    {
        return $this->hasAnyRole(['super-admin', 'admin', 'editor', 'contributor']);
    }

    /**
     * Canvas compatibility: Return a default avatar.
     *
     * @return string
     */
    public function getDefaultAvatarAttribute(): string
    {
        return 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($this->email ?? ''))) . '?d=mp';
    }

    /**
     * Canvas compatibility: Return a default locale.
     *
     * @return string
     */
    public function getDefaultLocaleAttribute(): string
    {
        return config('app.locale');
    }
}
