<?php

namespace Tests\Feature;

use App\Events\MediaPlaybackMilestoneReached;
use App\Listeners\InvalidateCurrentlyWatchingCache;
use App\Models\LocalMedia;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class InvalidateCurrentlyWatchingCacheTest extends TestCase
{
    use DatabaseTransactions;

    public function test_milestone_event_invalidates_currently_watching_cache(): void
    {
        $media = LocalMedia::factory()->create([
            'last_playback_at' => now(),
        ]);

        $this->getJson('/api/currently-watching')->assertOk();

        $this->assertTrue(Cache::has(LocalMedia::CURRENTLY_WATCHING_CACHE_KEY));

        $event = new MediaPlaybackMilestoneReached($media, 9500, 10000, CarbonImmutable::now());
        (new InvalidateCurrentlyWatchingCache)->handle($event);

        $this->assertFalse(Cache::has(LocalMedia::CURRENTLY_WATCHING_CACHE_KEY));
    }
}
