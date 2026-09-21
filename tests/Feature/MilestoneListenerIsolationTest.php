<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MilestoneListenerIsolationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_one_listener_failing_does_not_block_the_others_or_the_webhook_response(): void
    {
        // Force InvalidateCurrentlyWatchingCache's Cache::forget() call to throw,
        // simulating a failing consumer without touching its implementation.
        Cache::shouldReceive('forget')
            ->atLeast()->once()
            ->andThrow(new \RuntimeException('forced cache failure'));

        Log::shouldReceive('error')
            ->atLeast()->once()
            ->with('Failed to invalidate currently-watching cache', \Mockery::type('array'));
        Log::shouldReceive('info')
            ->atLeast()->once()
            ->with('social post stub: would post Finished Movie');

        $response = $this->postJson('/api/event/@2028', [
            'NotificationType' => 'PlaybackProgress',
            'ItemId' => 'item-isolation-1',
            'ItemType' => 'Movie',
            'Name' => 'Finished Movie',
            'UserId' => 'user-1',
            'PlaybackPositionTicks' => 9500,
            'RunTimeTicks' => 10000,
            'IsPaused' => false,
        ]);

        $response->assertOk();

        // RecordRecentlyFinishedMedia (registered before the failing listener)
        // still completed its own effect.
        $this->assertDatabaseHas('media_playback_milestones', [
            'title' => 'Finished Movie',
        ]);
    }
}
