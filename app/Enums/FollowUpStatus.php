<?php

namespace App\Enums;

/**
 * Where an application follow-up stands. A draft is `pending` until the
 * admin approves it (`sending`, then `sent` or `failed`) or it is
 * `discarded`. Only `pending` can be edited; `pending` and `failed` can be
 * approved.
 */
enum FollowUpStatus: string
{
    case Pending = 'pending';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Discarded = 'discarded';

    /**
     * Whether approving this follow-up may send it: a draft, or a send that
     * failed and is being retried.
     */
    public function isApprovable(): bool
    {
        return $this === self::Pending || $this === self::Failed;
    }

    /**
     * Whether the follow-up holds the application's one open slot, so no
     * other draft may be made beside it.
     */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Sending;
    }
}
