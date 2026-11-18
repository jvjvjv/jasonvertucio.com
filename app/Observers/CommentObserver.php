<?php

namespace App\Observers;

use App\Jobs\NotifyCommentReplyRecipient;
use App\Mail\CommentReceivedMail;
use App\Models\Comment;
use App\Support\CommentReplyEmails;
use Illuminate\Support\Facades\Mail;

class CommentObserver
{
    /**
     * Handle the comment "created" event.
     */
    public function created(Comment $comment): void
    {
        if (! $this->isOwnComment($comment)) {
            Mail::to(config('comments.notification_email'))->send(new CommentReceivedMail($comment));
        }

        if (! config('comments.reply_notifications.enabled')) {
            return;
        }

        $this->requestReplyEmailConfirmation($comment);

        if ($comment->parent_id !== null) {
            NotifyCommentReplyRecipient::dispatch($comment->getKey())
                ->delay(now()->addMinutes((int) config('comments.reply_notifications.delay_minutes')));
        }
    }

    /**
     * Ask a commenter who wants reply emails, and was not confirmed at
     * posting (anonymous, or signed in without a verified address), to
     * confirm the address. A confirmation withheld by the daily cap leaves
     * the subscription pending.
     */
    protected function requestReplyEmailConfirmation(Comment $comment): void
    {
        if (! $comment->notify_replies || $comment->notify_replies_confirmed_at !== null) {
            return;
        }

        CommentReplyEmails::requestConfirmation($comment);
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
