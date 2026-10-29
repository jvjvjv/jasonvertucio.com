<?php

namespace App\Listeners;

use App\Events\IpBanned;
use Illuminate\Support\Facades\Log;

class SendIpBanAlert
{
    public function handle(IpBanned $event): void
    {
        Log::notice('IP banned', [
            'ip' => $event->ip,
            'source' => $event->source->value,
            'banned_at' => now()->toIso8601String(),
        ]);

        $this->notifyExternally($event);
    }

    /**
     * Placeholder for a Slack/webhook notification. It must stay non-blocking:
     * the listener runs inside the request that created the ban, so a live
     * call belongs on the queue.
     */
    protected function notifyExternally(IpBanned $event): void
    {
        // TODO: wire a queued Slack/webhook notification.
    }
}
