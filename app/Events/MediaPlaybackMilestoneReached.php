<?php

namespace App\Events;

use App\Models\LocalMedia;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

class MediaPlaybackMilestoneReached
{
    use Dispatchable;

    public function __construct(
        public readonly LocalMedia $media,
        public readonly int $playbackPositionTicks,
        public readonly int $playbackDurationTicks,
        public readonly CarbonImmutable $reachedAt,
    ) {}
}
