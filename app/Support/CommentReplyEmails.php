<?php

namespace App\Support;

use App\Mail\CommentReplyConfirmationMail;
use App\Models\Comment;
use App\Models\CommentEmailOptOut;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;

/**
 * Links, confirmation requests and per-address throttle keys for comment
 * reply emails.
 *
 * Links are signed relative to the path, not the host, so a signature made
 * behind Cloudflare and the proxy still verifies whatever scheme or host the
 * request arrives with. Throttle keys hash the address, so no address ends up
 * in a cache key or cache file name.
 */
class CommentReplyEmails
{
    /**
     * Queue a confirmation email for a comment, unless its address has had
     * its share of confirmation emails today. Returns whether one was queued.
     *
     * An opted-out address is not skipped: the opt-out is not permanent, and
     * is lifted only when the address owner completes the confirmation.
     */
    public static function requestConfirmation(Comment $comment): bool
    {
        if (blank($comment->email)) {
            return false;
        }

        if (! self::takeFromCap(
            self::confirmationThrottleKey($comment->email),
            (int) config('comments.reply_notifications.confirmations_per_address_per_day'),
            86400,
        )) {
            return false;
        }

        Mail::to($comment->email)->send(new CommentReplyConfirmationMail($comment));

        return true;
    }

    /**
     * Count one send against a per-address cap, before sending.
     *
     * The hit is an atomic cache increment taken first, so concurrent
     * requests cannot all pass a check before any of them is counted.
     */
    public static function takeFromCap(string $key, int $max, int $decaySeconds): bool
    {
        return RateLimiter::hit($key, $decaySeconds) <= $max;
    }

    /**
     * The link the "send me a new link" button posts to, signed for an hour.
     */
    public static function resendUrl(Comment $comment): string
    {
        return URL::temporarySignedRoute(
            'comments.reply-emails.resend',
            now()->addHour(),
            ['comment' => $comment->getKey()],
            absolute: false,
        );
    }

    /**
     * The expiring link to the page that confirms a comment's subscription.
     */
    public static function confirmUrl(Comment $comment): string
    {
        $relative = URL::temporarySignedRoute(
            'comments.reply-emails.confirm',
            now()->addDays((int) config('comments.reply_notifications.confirmation_expires_days')),
            ['comment' => $comment->getKey()],
            absolute: false,
        );

        return url($relative);
    }

    /**
     * The non-expiring unsubscribe link. Its page is a GET of this URL, and a
     * one-click unsubscribe is a POST to the same URL.
     */
    public static function unsubscribeUrl(Comment $comment): string
    {
        $relative = URL::signedRoute(
            'comments.reply-emails.unsubscribe',
            ['comment' => $comment->getKey()],
            absolute: false,
        );

        return url($relative);
    }

    /**
     * The throttle key counting confirmation emails sent to an address.
     */
    public static function confirmationThrottleKey(string $email): string
    {
        return 'comment-reply-confirm:'.sha1(CommentEmailOptOut::normalize($email));
    }

    /**
     * The throttle key counting reply emails sent to an address.
     */
    public static function notificationThrottleKey(string $email): string
    {
        return 'comment-reply-notify:'.sha1(CommentEmailOptOut::normalize($email));
    }

    /**
     * Determine whether two addresses are the same, ignoring case.
     */
    public static function sameAddress(?string $first, ?string $second): bool
    {
        return filled($first)
            && filled($second)
            && CommentEmailOptOut::normalize($first) === CommentEmailOptOut::normalize($second);
    }

    /**
     * An address with most of its local part hidden, for display on a page
     * anyone holding the link can open.
     */
    public static function maskedAddress(?string $email): string
    {
        if (blank($email) || ! str_contains($email, '@')) {
            return 'this address';
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).str_repeat('•', max(mb_strlen($local) - 1, 2)).'@'.$domain;
    }
}
