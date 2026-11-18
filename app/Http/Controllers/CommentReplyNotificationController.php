<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnsubscribeCommentReplyEmailsRequest;
use App\Models\Comment;
use App\Models\CommentEmailOptOut;
use App\Support\ClientAddress;
use App\Support\CommentReplyEmails;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * The pages behind the links in comment reply emails.
 *
 * Every route is signed. A GET only ever shows a page with a button, and the
 * button's POST is what changes anything, so a mail scanner that prefetches a
 * link cannot confirm or unsubscribe anyone.
 *
 * A link that fails its signature check still lands here, through
 * invalidLink(), so the person sees why rather than a bare 403.
 */
class CommentReplyNotificationController extends Controller
{
    /**
     * Show the confirm button for a pending subscription.
     */
    public function showConfirm(Comment $comment): Response
    {
        return $this->confirmPage($comment, $this->confirmState($comment));
    }

    /**
     * Confirm a pending subscription, if the comment is still there to confirm.
     *
     * Confirming proves the owner of the address the confirmation went to
     * (the one recorded on the comment) wants comment emails, so it also
     * lifts any earlier opt-out of that address. An opt-out made from
     * another spelling of the mailbox stays, and the page says so.
     */
    public function confirm(Comment $comment): Response
    {
        $state = $this->confirmState($comment);

        if ($state === 'pending') {
            $comment->notify_replies_confirmed_at = now();
            $comment->save();

            CommentEmailOptOut::lift($comment->email);

            $state = 'confirmed';
        }

        return $this->confirmPage($comment, $state);
    }

    /**
     * Show the two ways to stop comment emails.
     */
    public function showUnsubscribe(Comment $comment): Response
    {
        return $this->unsubscribePage($comment, null);
    }

    /**
     * Stop reply emails for this comment, or every comment email to its
     * address. Also the target of a mail client's one-click unsubscribe.
     */
    public function unsubscribe(UnsubscribeCommentReplyEmailsRequest $request, Comment $comment): Response
    {
        if ($comment->notify_replies) {
            $comment->notify_replies = false;
            $comment->save();
        }

        $address = $comment->replyNotificationAddress();

        if ($request->optsOutAddress() && filled($address)) {
            CommentEmailOptOut::optOut($address);
        }

        return $this->unsubscribePage(
            $comment,
            $request->optsOutAddress() ? UnsubscribeCommentReplyEmailsRequest::SCOPE_ADDRESS : UnsubscribeCommentReplyEmailsRequest::SCOPE_COMMENT,
        );
    }

    /**
     * Email a fresh confirmation link, from the button on an expired link's
     * page. Answers 429 once the requester's IP, or the mailbox, has had its
     * share for the day.
     */
    public function resend(Request $request, Comment $comment): Response
    {
        $state = $this->confirmState($comment);

        if ($state !== 'pending') {
            return $this->linkPage($comment, $state === 'confirmed' ? 'already-confirmed' : 'unavailable', 410);
        }

        return CommentReplyEmails::requestConfirmation($comment, ClientAddress::from($request))
            ? $this->linkPage($comment, 'resent', 200)
            : $this->linkPage($comment, 'capped', 429);
    }

    /**
     * Explain a link that failed its signature check, keeping the 403.
     *
     * A confirmation link the site really issued but which has run out says
     * so, and offers a new one while the comment is still waiting to be
     * confirmed. Anything else (a missing or altered signature) is simply not
     * valid, with nothing to press.
     */
    public function invalidLink(Request $request): Response
    {
        $comment = $request->route('comment');

        $isExpired = $comment instanceof Comment
            && $request->routeIs('comments.reply-emails.confirm', 'comments.reply-emails.confirm.store', 'comments.reply-emails.resend')
            && URL::hasCorrectSignature($request, false)
            && ! URL::signatureHasNotExpired($request);

        if (! $isExpired) {
            return $this->linkPage(null, 'invalid', 403);
        }

        $state = match ($this->confirmState($comment)) {
            'confirmed' => 'already-confirmed',
            'unavailable' => 'unavailable',
            default => 'expired',
        };

        return $this->linkPage($comment, $state, 403);
    }

    /**
     * Where a comment's subscription stands, as far as confirming goes.
     */
    protected function confirmState(Comment $comment): string
    {
        return match (true) {
            ! $comment->isVisible(), ! $comment->notify_replies => 'unavailable',
            $comment->hasActiveReplySubscription() => 'confirmed',
            default => 'pending',
        };
    }

    /**
     * Render the page for an expired or invalid link, or the outcome of
     * asking for a new one.
     */
    protected function linkPage(?Comment $comment, string $state, int $status): Response
    {
        return response()->view('comment-reply-emails.link', [
            'state' => $state,
            'postTitle' => $comment === null ? null : $this->postTitle($comment),
            'postUrl' => $comment === null ? null : $this->postUrl($comment),
            'maskedAddress' => CommentReplyEmails::maskedAddress($comment?->replyNotificationAddress()),
            'resendUrl' => $state === 'expired' ? CommentReplyEmails::resendUrl($comment) : null,
            'expiresInDays' => (int) config('comments.reply_notifications.confirmation_expires_days'),
        ], $status);
    }

    /**
     * Render the confirm page in the given state; an unconfirmable comment
     * answers 410.
     */
    protected function confirmPage(Comment $comment, string $state): Response
    {
        if ($state === 'confirmed' && CommentEmailOptOut::coveredByAnotherAddress($comment->replyNotificationAddress())) {
            $state = 'confirmed-blocked';
        }

        return response()->view('comment-reply-emails.confirm', [
            'state' => $state,
            'postTitle' => $this->postTitle($comment),
            'postUrl' => $this->postUrl($comment),
            'action' => request()->getRequestUri(),
        ], $state === 'unavailable' ? 410 : 200);
    }

    /**
     * Render the unsubscribe page, before an action ($done null) or after one.
     */
    protected function unsubscribePage(Comment $comment, ?string $done): Response
    {
        return response()->view('comment-reply-emails.unsubscribe', [
            'done' => $done,
            'postTitle' => $this->postTitle($comment),
            'postUrl' => $this->postUrl($comment),
            'maskedAddress' => CommentReplyEmails::maskedAddress($comment->replyNotificationAddress()),
            'action' => request()->getRequestUri(),
        ]);
    }

    /**
     * The post's title, only while the post is published: anyone holding a
     * link can open these pages, and a draft's title is not public.
     */
    protected function postTitle(Comment $comment): ?string
    {
        return $comment->post?->published ? $comment->post->title : null;
    }

    /**
     * A link back to the post, only while it is published and the comment
     * can still be seen on it.
     */
    protected function postUrl(Comment $comment): ?string
    {
        if ($comment->post === null || ! $comment->post->published || ! $comment->isVisible()) {
            return null;
        }

        return route('post', $comment->post->slug).'#comment-'.$comment->getKey();
    }
}
