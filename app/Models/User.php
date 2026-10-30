<?php

namespace App\Models;

use BSPDX\Keystone\Traits\HasKeystone;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasKeystone, HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
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
     * The two-factor columns are no longer used (bspdx.com owns MFA) but still
     * hold legacy secrets until a cleanup migration drops them.
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
        'show_tool_payloads' => 'boolean',
        'dark_mode' => 'boolean',
        'digest' => 'boolean',
        'role' => 'integer',
    ];

    /**
     * The external sign-in identities linked to this user.
     *
     * @return HasMany<FederatedIdentity, $this>
     */
    public function federatedIdentities(): HasMany
    {
        return $this->hasMany(FederatedIdentity::class);
    }

    /**
     * The linked bspdx.com identity, if any.
     */
    public function bspdxIdentity(): ?FederatedIdentity
    {
        return $this->federatedIdentities->firstWhere('provider', FederatedIdentity::PROVIDER_BSPDX);
    }

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
