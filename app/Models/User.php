<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use BSPDX\AuthKit\Traits\HasAuthKit;
use BSPDX\AuthKit\Contracts\HasPasskeys;

class User extends Authenticatable implements HasPasskeys
{
    use Notifiable, HasAuthKit;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
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
    ];

    /**
     * Check if user can manage Canvas blog posts.
     * Now uses RBAC instead of hardcoded email check.
     *
     * @return bool
     */
    public function canManageBinshopsBlogPosts(): bool
    {
        return $this->hasAnyRole(['super-admin', 'admin', 'editor']);
    }
}
