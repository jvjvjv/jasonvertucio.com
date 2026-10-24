<?php

namespace App\Listeners;

use App\Events\MediaPlaybackMilestoneReached;
use Illuminate\Support\Facades\Log;
use Throwable;

class LogSocialPostStub
{
    public function handle(MediaPlaybackMilestoneReached $event): void
    {
        try {
            Log::info("social post stub: would post {$event->media->title}");
        } catch (Throwable $exception) {
            Log::error('Failed to log social post stub', [
                'local_media_id' => $event->media->id,
                'exception' => $exception,
            ]);
        }
    }
}
