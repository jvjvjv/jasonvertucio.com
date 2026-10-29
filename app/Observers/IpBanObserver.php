<?php

namespace App\Observers;

use App\Events\IpBanned;
use App\Models\IpBan;
use Illuminate\Support\Facades\Cache;

class IpBanObserver
{
    /**
     * Enforce and announce every new ban, whichever producer created it.
     */
    public function created(IpBan $ipBan): void
    {
        Cache::forget('banned_ip_list');

        IpBanned::dispatch($ipBan->ip, $ipBan->source, $ipBan);
    }

    /**
     * Stop enforcing a lifted ban on the next request.
     */
    public function deleted(IpBan $ipBan): void
    {
        Cache::forget('banned_ip_list');
    }
}
