<?php

namespace App\Jobs;

use App\Mail\CommentReplyMail;
use App\Models\Comment;
use App\Models\CommentEmailOptOut;
use App\Support\CommentReplyEmails;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the author of the comment a reply answers, if they still should be.
 *
 * Dispatched with a delay when a reply is created, and every condition is
 * checked here, when the email is about to go, rather than at dispatch: spam,
 * deletion, unpublishing and unsubscribing during the delay must all stop it.
 * Holds the reply's id rather than the model, so a row that has gone away is
 * a quiet no-op instead of a failed job.
 */
class NotifyCommentReplyRecipient implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $replyId) {}

    public function handle(): void
    {
        if (! config('comments.reply_notifications.enabled')) {
            return;
        }

        $reply = Comment::withTrashed()->with(['user', 'post'])->find($this->replyId);
        $parent = $reply?->parent_id === null
            ? null
            : Comment::withTrashed()->with(['user', 'post'])->find($reply->parent_id);

        if ($reply === null || $parent === null || ! $this->shouldNotify($reply, $parent)) {
            return;
        }

        $address = $parent->replyNotificationAddress();

        $withinCap = CommentReplyEmails::takeFromCap(
            CommentReplyEmails::notificationThrottleKey($address),
            (int) config('comments.reply_notifications.notifications_per_address_per_hour'),
            3600,
        );

        if (! $withinCap) {
            Log::info('Comment reply email withheld: hourly cap reached for the recipient.', [
                'reply_id' => $reply->getKey(),
                'parent_id' => $parent->getKey(),
            ]);

            return;
        }

        Mail::to($address)->send(new CommentReplyMail($reply, $parent));
    }

    /**
     * Determine whether the parent's author is to hear about this reply now.
     */
    protected function shouldNotify(Comment $reply, Comment $parent): bool
    {
        $address = $parent->replyNotificationAddress();

        return $reply->isVisible()
            && $parent->isVisible()
            && $parent->post !== null
            && $parent->post->published
            && $parent->hasActiveReplySubscription()
            && filled($address)
            && ! CommentEmailOptOut::covers($address)
            && ! $this->isSameAuthor($reply, $parent)
            && ! CommentReplyEmails::sameAddress($address, config('comments.owner_email'));
    }

    /**
     * Determine whether someone is replying to their own comment, as the same
     * account or under the same address.
     */
    protected function isSameAuthor(Comment $reply, Comment $parent): bool
    {
        if ($reply->user_id !== null && $reply->user_id === $parent->user_id) {
            return true;
        }

        return CommentReplyEmails::sameAddress(
            $reply->replyNotificationAddress(),
            $parent->replyNotificationAddress(),
        );
    }
}
