<?php

namespace App\Observers;

use App\Events\IpBanned;
use App\Models\IpBan;

class IpBanObserver
{
    /**
     * Announce every new ban, whichever producer created it.
     */
    public function created(IpBan $ipBan): void
    {
        IpBanned::dispatch($ipBan->ip, $ipBan->source, $ipBan);
    }
}
