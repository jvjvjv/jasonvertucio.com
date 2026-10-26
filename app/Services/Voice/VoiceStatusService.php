<?php

namespace App\Services\Voice;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jvjvjv\CodeTalker\Enums\AiSystemRole;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * Whether dictation and speech can be used right now, so the controls can be
 * disabled before the owner speaks rather than failing afterwards.
 */
class VoiceStatusService
{
    public const string DICTATION = 'voice:dictation';

    public const string SPEECH = 'voice:speech';

    /**
     * @return array{dictation: array{available: bool, reason: string|null}, speech: array{available: bool, reason: string|null}}
     */
    public function status(): array
    {
        return [
            'dictation' => $this->feature(self::DICTATION, AiSystemRole::Transcription),
            'speech' => $this->feature(self::SPEECH, AiSystemRole::Audio),
        ];
    }

    /**
     * @return array{available: bool, reason: string|null}
     */
    private function feature(string $feature, AiSystemRole $role): array
    {
        $system = AiSystem::defaultForFeature($feature);

        if ($system === null || $system->role !== $role) {
            return ['available' => false, 'reason' => 'not_configured'];
        }

        if (! $system->is_active) {
            return ['available' => false, 'reason' => 'inactive'];
        }

        if (blank($system->base_url) || $this->reachable($system)) {
            return ['available' => true, 'reason' => null];
        }

        return ['available' => false, 'reason' => 'unreachable'];
    }

    /**
     * A system on a provider's own cloud endpoint is never probed; one with a
     * base URL (a speech server at home) is asked for its model list. Any
     * answer short of a server error counts: the question is whether the
     * server is there, not whether this request was well-formed.
     */
    private function reachable(AiSystem $system): bool
    {
        $baseUrl = rtrim((string) $system->base_url, '/');
        $key = "voice-status:{$system->id}:".hash('sha256', $baseUrl);

        return Cache::remember($key, (int) config('voice.status_cache_seconds'), function () use ($system, $baseUrl): bool {
            $timeout = (int) config('voice.status_probe_timeout');

            try {
                $request = Http::timeout($timeout)->connectTimeout($timeout)->acceptJson();

                if (filled($system->api_key)) {
                    $request = $request->withToken((string) $system->api_key);
                }

                return $request->get("{$baseUrl}/models")->status() < 500;
            } catch (ConnectionException) {
                return false;
            }
        });
    }
}
