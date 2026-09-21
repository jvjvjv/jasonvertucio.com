<?php

namespace Tests\Feature;

use App\Events\MediaPlaybackMilestoneReached;
use App\Models\LocalMedia;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LocalMediaMilestoneTest extends TestCase
{
    use DatabaseTransactions;

    private function playbackPayload(array $overrides = []): array
    {
        return array_merge([
            'NotificationType' => 'PlaybackProgress',
            'ItemId' => 'item-milestone-1',
            'ItemType' => 'Movie',
            'Name' => 'Test Movie',
            'UserId' => 'user-1',
            'PlaybackPositionTicks' => 5000,
            'RunTimeTicks' => 10000,
            'IsPaused' => false,
        ], $overrides);
    }

    public function test_crossing_threshold_dispatches_event_exactly_once(): void
    {
        Event::fake([MediaPlaybackMilestoneReached::class]);

        $this->postJson('/api/event/@2028', $this->playbackPayload([
            'PlaybackPositionTicks' => 9000,
            'RunTimeTicks' => 10000,
        ]))->assertOk();

        Event::assertDispatchedTimes(MediaPlaybackMilestoneReached::class, 1);
    }

    public function test_second_progress_past_threshold_does_not_dispatch_again(): void
    {
        Event::fake([MediaPlaybackMilestoneReached::class]);

        $this->postJson('/api/event/@2028', $this->playbackPayload([
            'PlaybackPositionTicks' => 9000,
            'RunTimeTicks' => 10000,
        ]))->assertOk();

        $this->postJson('/api/event/@2028', $this->playbackPayload([
            'PlaybackPositionTicks' => 9500,
            'RunTimeTicks' => 10000,
        ]))->assertOk();

        Event::assertDispatchedTimes(MediaPlaybackMilestoneReached::class, 1);
    }

    public function test_zero_runtime_never_dispatches(): void
    {
        Event::fake([MediaPlaybackMilestoneReached::class]);

        $this->postJson('/api/event/@2028', $this->playbackPayload([
            'PlaybackPositionTicks' => 9000,
            'RunTimeTicks' => 0,
        ]))->assertOk();

        $this->postJson('/api/event/@2028', $this->playbackPayload([
            'PlaybackPositionTicks' => 9000,
            'RunTimeTicks' => null,
        ]))->assertOk();

        Event::assertNotDispatched(MediaPlaybackMilestoneReached::class);
    }

    public function test_playback_start_after_completed_session_resets_flag_and_allows_new_milestone(): void
    {
        Event::fake([MediaPlaybackMilestoneReached::class]);

        $this->postJson('/api/event/@2028', $this->playbackPayload([
            'PlaybackPositionTicks' => 9000,
            'RunTimeTicks' => 10000,
        ]))->assertOk();

        Event::assertDispatchedTimes(MediaPlaybackMilestoneReached::class, 1);

        $media = LocalMedia::where('jellyfin_item_id', 'item-milestone-1')->first();
        $this->assertNotNull($media->milestone_reached_at);

        $this->postJson('/api/event/@2028', $this->playbackPayload([
            'NotificationType' => 'PlaybackStart',
            'PlaybackPositionTicks' => 0,
            'RunTimeTicks' => 10000,
        ]))->assertOk();

        $media->refresh();
        $this->assertNull($media->milestone_reached_at);

        $this->postJson('/api/event/@2028', $this->playbackPayload([
            'PlaybackPositionTicks' => 9200,
            'RunTimeTicks' => 10000,
        ]))->assertOk();

        Event::assertDispatchedTimes(MediaPlaybackMilestoneReached::class, 2);
    }
}
