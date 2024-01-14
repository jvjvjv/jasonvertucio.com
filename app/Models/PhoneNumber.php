<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhoneNumber extends Model
{
    use Softdeletes;

    protected $fillable = ['phone_number', 'active'];
    protected $hidden = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];
}
