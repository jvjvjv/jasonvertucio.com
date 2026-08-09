<?php

namespace App\Observers;

use App\Mail\CommentReceivedMail;
use App\Models\Comment;
use Illuminate\Support\Facades\Mail;

class CommentObserver
{
    /**
     * Handle the comment "created" event.
     *
     * @return void
     */
    public function created(Comment $comment)
    {
        Mail::to('me@jasonvertucio.com')->send(new CommentReceivedMail($comment));
    }

    /**
     * Handle the comment "updated" event.
     *
     * @return void
     */
    public function updated(Comment $comment)
    {
        //
    }

    /**
     * Handle the comment "deleted" event.
     *
     * @return void
     */
    public function deleted(Comment $comment)
    {
        //
    }

    /**
     * Handle the comment "restored" event.
     *
     * @return void
     */
    public function restored(Comment $comment)
    {
        //
    }

    /**
     * Handle the comment "force deleted" event.
     *
     * @return void
     */
    public function forceDeleted(Comment $comment)
    {
        //
    }
}
