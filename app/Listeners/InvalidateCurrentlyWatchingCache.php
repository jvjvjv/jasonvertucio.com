<?php

namespace App\Listeners;

use App\Events\MediaPlaybackMilestoneReached;
use App\Models\LocalMedia;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class InvalidateCurrentlyWatchingCache
{
    public function handle(MediaPlaybackMilestoneReached $event): void
    {
        try {
            Cache::forget(LocalMedia::CURRENTLY_WATCHING_CACHE_KEY);
        } catch (Throwable $exception) {
            Log::error('Failed to invalidate currently-watching cache', [
                'local_media_id' => $event->media->id,
                'exception' => $exception,
            ]);
        }
    }
}
