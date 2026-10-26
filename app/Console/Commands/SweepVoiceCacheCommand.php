<?php

namespace App\Console\Commands;

use App\Services\Voice\SpeechCache;
use Illuminate\Console\Command;

/**
 * Removes generated speech older than `voice.cache_ttl_days`. Entries are
 * never invalidated otherwise: the key changes whenever the audio would.
 */
class SweepVoiceCacheCommand extends Command
{
    protected $signature = 'voice:sweep-cache';

    protected $description = 'Delete cached speech older than the voice cache retention window';

    public function handle(SpeechCache $cache): int
    {
        $cutoff = now()->subDays((int) config('voice.cache_ttl_days'))->timestamp;
        $deleted = $cache->sweep($cutoff);

        $this->info("Deleted {$deleted} cached speech file(s).");

        return self::SUCCESS;
    }
}
