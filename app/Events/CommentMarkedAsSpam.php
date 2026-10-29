<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Foundation\Events\Dispatchable;

class CommentMarkedAsSpam
{
    use Dispatchable;

    public function __construct(public readonly Comment $comment) {}
}
