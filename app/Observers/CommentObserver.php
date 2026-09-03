<?php

namespace App\Observers;

use App\Mail\CommentReceivedMail;
use App\Models\Comment;
use Illuminate\Support\Facades\Mail;

class CommentObserver
{
    /**
     * Handle the comment "created" event.
     */
    public function created(Comment $comment): void
    {
        if ($this->isOwnComment($comment)) {
            return;
        }

        Mail::to(config('comments.notification_email'))->send(new CommentReceivedMail($comment));
    }

    /**
     * Determine whether the site owner wrote this comment themselves.
     */
    protected function isOwnComment(Comment $comment): bool
    {
        $owner = config('comments.owner_email');

        if (blank($owner)) {
            return false;
        }

        $author = $comment->user?->email ?? $comment->email;

        return filled($author) && strcasecmp($author, $owner) === 0;
    }
}
