<?php

namespace App\Support;

use App\Mail\CommentReplyConfirmationMail;
use App\Models\Comment;
use App\Models\CommentEmailOptOut;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;

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
     * How long a send waits for another request counting against the same
     * cap before giving up and sending nothing.
     */
    private const CAP_LOCK_WAIT_SECONDS = 2;

    /**
     * Queue a confirmation email for a comment, unless the client asking, or
     * its mailbox, has had its share of confirmation emails today. Returns
     * whether one was queued.
     *
     * The client's cap is taken first, and a client over it spends nothing
     * from the mailbox's cap, so a blocked client cannot use up the share of
     * an inbox it does not own. A send the mailbox cap refuses still spends a
     * count from the client's; that only ever sends less. A blank client IP
     * shares one bucket. An opted-out address is not skipped: the opt-out is
     * not permanent, and is lifted only when the address owner completes the
     * confirmation.
     *
     * Nothing is sent to an address the comment form would refuse (see
     * isPlainAddress()). The form already checks anonymous comments; this
     * covers a signed-in user's account address, which bspdx.com supplies.
     */
    public static function requestConfirmation(Comment $comment, ?string $clientIp): bool
    {
        if (blank($comment->email) || ! self::isPlainAddress($comment->email)) {
            return false;
        }

        $withinIpCap = self::takeFromCap(
            self::confirmationIpThrottleKey($clientIp),
            (int) config('comments.reply_notifications.confirmations_per_ip_per_day'),
            86400,
        );

        if (! $withinIpCap) {
            return false;
        }

        $withinAddressCap = self::takeFromCap(
            self::confirmationThrottleKey($comment->email),
            (int) config('comments.reply_notifications.confirmations_per_address_per_day'),
            86400,
        );

        if (! $withinAddressCap) {
            return false;
        }

        Mail::to($comment->email)->send(new CommentReplyConfirmationMail($comment));

        return true;
    }

    /**
     * Count one send against a cap, before sending.
     *
     * The count is taken under a cache lock on the key. RateLimiter::hit()
     * is only atomic on some stores: the file store, which production uses,
     * reads the count and writes it back, so without the lock concurrent
     * requests could all read the same count and all pass. A send that
     * cannot get the lock in time is refused, never sent uncounted.
     */
    public static function takeFromCap(string $key, int $max, int $decaySeconds): bool
    {
        try {
            return Cache::lock('lock:'.$key, 10)->block(
                self::CAP_LOCK_WAIT_SECONDS,
                fn (): bool => RateLimiter::hit($key, $decaySeconds) <= $max,
            );
        } catch (LockTimeoutException) {
            return false;
        }
    }

    /**
     * Determine whether an address is one the comment form accepts: valid,
     * with no RFC warnings. Quoted local parts and comments, which reach a
     * mailbox the mailbox key cannot fold, are refused.
     */
    public static function isPlainAddress(string $email): bool
    {
        return Validator::make(['email' => $email], ['email' => ['email:strict']])->passes();
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
     * The throttle key counting confirmation emails one client has caused.
     */
    public static function confirmationIpThrottleKey(?string $clientIp): string
    {
        return 'comment-reply-confirm-ip:'.sha1(filled($clientIp) ? $clientIp : 'unknown');
    }

    /**
     * The throttle key counting reply emails sent to an address.
     */
    public static function notificationThrottleKey(string $email): string
    {
        return 'comment-reply-notify:'.sha1(CommentEmailOptOut::normalize($email));
    }

    /**
     * Determine whether two addresses are the same mailbox.
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
