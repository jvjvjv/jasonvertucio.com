<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CanvasPostsTopics extends Model
{
    public $dates = false;

    protected $fillable = ['post_id', 'tag_id'];
}
