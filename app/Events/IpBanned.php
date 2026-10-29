<?php

namespace App\Events;

use App\Enums\IpBanSource;
use App\Models\IpBan;
use Illuminate\Foundation\Events\Dispatchable;

class IpBanned
{
    use Dispatchable;

    public function __construct(
        public readonly string $ip,
        public readonly IpBanSource $source,
        public readonly IpBan $ipBan,
    ) {}
}
