<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use softDeletes;

    protected $fillable = [
        'parent_id',
        'fb_comment_id',
        'fb_parent_comment_id',
        'name',
        'email',
        'message',
        'created_at',
        'updated_at',
    ];
}
