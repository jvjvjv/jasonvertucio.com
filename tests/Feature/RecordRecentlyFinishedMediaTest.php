<?php

namespace Tests\Feature;

use App\Events\MediaPlaybackMilestoneReached;
use App\Listeners\RecordRecentlyFinishedMedia;
use App\Models\LocalMedia;
use App\Models\MediaPlaybackMilestone;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RecordRecentlyFinishedMediaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dispatched_event_records_milestone_history_row(): void
    {
        $media = LocalMedia::factory()->create([
            'title' => 'Finished Movie',
            'media_type' => 'movie',
        ]);

        $reachedAt = CarbonImmutable::now();
        $event = new MediaPlaybackMilestoneReached($media, 9500, 10000, $reachedAt);

        (new RecordRecentlyFinishedMedia)->handle($event);

        $this->assertDatabaseHas('media_playback_milestones', [
            'local_media_id' => $media->id,
            'title' => 'Finished Movie',
            'media_type' => 'movie',
        ]);

        $milestone = MediaPlaybackMilestone::where('local_media_id', $media->id)->first();
        $this->assertNotNull($milestone);
        $this->assertSame($reachedAt->format('Y-m-d H:i:s'), $milestone->finished_at->format('Y-m-d H:i:s'));
    }
}
