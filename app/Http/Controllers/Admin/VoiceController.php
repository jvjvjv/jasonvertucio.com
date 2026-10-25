<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SynthesizeSpeechRequest;
use App\Http\Requests\TranscribeVoiceRequest;
use App\Services\Voice\SpeechCache;
use App\Services\Voice\VoiceStatusService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Jvjvjv\CodeTalker\Enums\AiSystemRole;
use Jvjvjv\CodeTalker\Models\AiSystem;
use Jvjvjv\CodeTalker\Services\AiSystemResolver;
use Jvjvjv\CodeTalker\Services\Audio\SpeechService;
use Jvjvjv\CodeTalker\Services\Audio\TranscriptionService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Dictation, read-aloud and proofreading for holders of `use-voice`, backed by
 * the `voice:dictation` and `voice:speech` default systems.
 */
class VoiceController extends Controller
{
    public function __construct(
        private VoiceStatusService $status,
        private AiSystemResolver $resolver,
        private TranscriptionService $transcriptions,
        private SpeechService $speech,
        private SpeechCache $cache,
    ) {}

    public function status(): JsonResponse
    {
        return response()->json($this->status->status());
    }

    /**
     * The recording is never stored: PHP discards its temporary upload when
     * the request ends.
     */
    public function transcribe(TranscribeVoiceRequest $request): JsonResponse
    {
        return $this->orUnavailable(VoiceStatusService::DICTATION, function () use ($request): JsonResponse {
            $response = $this->transcriptions->forFeature(
                VoiceStatusService::DICTATION,
                $request->file('audio'),
                $request->language(),
            );

            return response()->json(['text' => trim($response->text)]);
        });
    }

    public function speech(SynthesizeSpeechRequest $request): BinaryFileResponse|JsonResponse
    {
        return $this->orUnavailable(VoiceStatusService::SPEECH, function () use ($request): BinaryFileResponse {
            $system = $this->resolver->forFeature(VoiceStatusService::SPEECH, AiSystemRole::Audio);
            $text = $request->speakableText();
            $voice = $this->resolveVoice($system, $request->input('voice'));
            $key = $this->cache->key($system, $voice, $text);

            if (! $this->cache->has($key)) {
                $audio = $this->speech->forSystem($system, $text, $voice !== '' ? $voice : null);
                $this->cache->put($key, $audio->content());
            }

            return response()->file($this->cache->absolutePath($key), [
                'Content-Type' => 'audio/mpeg',
                'Cache-Control' => 'private, max-age=86400',
            ]);
        });
    }

    /**
     * The request's voice, else the system's default voice, else none (the
     * provider's own default). Resolved before hashing, so changing the
     * default voice changes the cache key.
     */
    private function resolveVoice(AiSystem $system, mixed $requested): string
    {
        if (is_string($requested) && trim($requested) !== '') {
            return trim($requested);
        }

        $default = $system->config['voice'] ?? null;

        return is_string($default) ? trim($default) : '';
    }

    /**
     * The one place a voice failure is turned into a response. A connection
     * failure, a timeout, a provider error or a misconfigured default all
     * become the same generic 503: the browser never sees the server's
     * address or the provider's error text, and the log keeps the detail.
     *
     * @template T
     *
     * @param  Closure(): T  $call
     * @return T|JsonResponse
     */
    private function orUnavailable(string $feature, Closure $call): mixed
    {
        try {
            return $call();
        } catch (Throwable $e) {
            Log::warning('Voice request failed.', [
                'feature' => $feature,
                'ai_system_id' => AiSystem::defaultForFeature($feature)?->id,
                'exception' => $e::class,
                'message' => $this->scrubbed($e->getMessage()),
            ]);

            return response()->json(['message' => 'Voice is unavailable.'], 503);
        }
    }

    /**
     * An HTTP client exception's message carries the request URL. Credentials
     * in a base URL (`http://user:pass@host`) or a key sent as a query string
     * must not reach the log, so both are cut before it is written.
     */
    private function scrubbed(string $message): string
    {
        $message = (string) preg_replace('#(https?://)[^/\s@]+@#i', '$1[credentials]@', $message);
        $message = (string) preg_replace('#(https?://[^\s?"\']+)\?[^\s"\']*#i', '$1?[query]', $message);

        return mb_substr($message, 0, 500);
    }
}
