<?php

namespace Tests\Unit\Events;

use App\Events\MediaPlaybackMilestoneReached;
use App\Models\LocalMedia;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class MediaPlaybackMilestoneReachedTest extends TestCase
{
    public function test_exposes_constructed_properties(): void
    {
        $media = new LocalMedia(['title' => 'Test Movie']);
        $reachedAt = CarbonImmutable::now();

        $event = new MediaPlaybackMilestoneReached($media, 9000, 10000, $reachedAt);

        $this->assertSame($media, $event->media);
        $this->assertSame(9000, $event->playbackPositionTicks);
        $this->assertSame(10000, $event->playbackDurationTicks);
        $this->assertSame($reachedAt, $event->reachedAt);
    }
}
