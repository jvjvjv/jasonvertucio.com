<?php

namespace App\Listeners;

use App\Events\MediaPlaybackMilestoneReached;
use App\Models\MediaPlaybackMilestone;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecordRecentlyFinishedMedia
{
    public function handle(MediaPlaybackMilestoneReached $event): void
    {
        try {
            MediaPlaybackMilestone::create([
                'local_media_id' => $event->media->id,
                'title' => $event->media->title,
                'media_type' => $event->media->media_type,
                'finished_at' => $event->reachedAt,
            ]);
        } catch (Throwable $exception) {
            Log::error('Failed to record recently finished media', [
                'local_media_id' => $event->media->id,
                'exception' => $exception,
            ]);
        }
    }
}
