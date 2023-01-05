<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CanvasPostsTags extends Model
{
    public $timestamps = false;

    protected $fillable = ['post_id', 'tag_id'];
}
