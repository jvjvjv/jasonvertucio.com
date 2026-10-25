<?php

namespace App\Services\Voice;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * Generated speech, kept as plain MP3 files on the local disk. The key covers
 * everything that changes the audio — the system, its model, the resolved
 * voice and the spoken text — so changing any of them misses.
 */
class SpeechCache
{
    public const string DIRECTORY = 'voice-cache';

    public function key(AiSystem $system, string $voice, string $text): string
    {
        return hash('sha256', implode("\0", [$system->id, (string) $system->model, $voice, $text]));
    }

    public function path(string $key): string
    {
        return self::DIRECTORY."/{$key}.mp3";
    }

    public function has(string $key): bool
    {
        return $this->disk()->exists($this->path($key));
    }

    public function put(string $key, string $audio): void
    {
        $this->disk()->put($this->path($key), $audio);
    }

    public function absolutePath(string $key): string
    {
        return $this->disk()->path($this->path($key));
    }

    /**
     * Delete entries last written before the cutoff.
     */
    public function sweep(int $olderThanTimestamp): int
    {
        $deleted = 0;

        foreach ($this->disk()->files(self::DIRECTORY) as $file) {
            if ($this->disk()->lastModified($file) < $olderThanTimestamp) {
                $this->disk()->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
