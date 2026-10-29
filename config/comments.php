<?php

return [

    /*
    |--------------------------------------------------------------------------
    | New Comment Notification Recipient
    |--------------------------------------------------------------------------
    |
    | Where CommentReceivedMail is delivered when a visitor leaves a comment.
    | Notifications are suppressed for comments authored by the site owner.
    |
    */

    'notification_email' => env('COMMENT_NOTIFICATION_EMAIL', 'me@jasonvertucio.com'),

    /*
    |--------------------------------------------------------------------------
    | Site Owner
    |--------------------------------------------------------------------------
    |
    | The address whose own comments never trigger a notification. Defaults to
    | the notification recipient, so the owner does not email themselves.
    |
    */

    'owner_email' => env('COMMENT_OWNER_EMAIL', env('COMMENT_NOTIFICATION_EMAIL', 'me@jasonvertucio.com')),

    /*
    |--------------------------------------------------------------------------
    | Submission Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum comment submissions accepted per minute from one client address,
    | resolved through the same Cloudflare-aware logic as IpMiddleware.
    |
    */

    'rate_limit_per_minute' => (int) env('COMMENT_RATE_LIMIT_PER_MINUTE', 5),

    /*
    |--------------------------------------------------------------------------
    | Repeat Spam Escalation
    |--------------------------------------------------------------------------
    |
    | When a comment is marked as spam, EscalateRepeatSpamToIpBan counts the
    | spam comments from the same address inside the rolling window and bans
    | the address once the count reaches the threshold.
    |
    */

    'spam_escalation' => [
        'enabled' => (bool) env('COMMENT_SPAM_ESCALATION_ENABLED', true),
        'threshold' => (int) env('COMMENT_SPAM_ESCALATION_THRESHOLD', 3),
        'window_hours' => (int) env('COMMENT_SPAM_ESCALATION_WINDOW_HOURS', 24),
    ],

];
