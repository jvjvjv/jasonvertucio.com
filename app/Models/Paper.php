<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paper extends Model
{
    use HasFactory;
    protected $fillable = ['edition_id', 'edition', 'published_at'];
    protected $hidden = ['updated_at', 'created_at'];
    protected $casts = [
        'published_at' => 'datetime'
    ];
}
