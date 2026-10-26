<?php

namespace App\Support;

use App\Models\User;

/**
 * The `voice` prop both Inertia middlewares share. It is null for anyone
 * without `use-voice`, so no voice control renders for them on any page,
 * public persona pages included.
 */
class VoiceProps
{
    /**
     * @return array{statusUrl: string, transcribeUrl: string, speechUrl: string, maxRecordingSeconds: int, maxSpeechChars: int}|null
     */
    public static function for(?User $user): ?array
    {
        if ($user === null || ! $user->can('use-voice')) {
            return null;
        }

        return [
            'statusUrl' => route('admin.voice.status', absolute: false),
            'transcribeUrl' => route('admin.voice.transcriptions', absolute: false),
            'speechUrl' => route('admin.voice.speech', absolute: false),
            'maxRecordingSeconds' => (int) config('voice.max_recording_seconds'),
            'maxSpeechChars' => (int) config('voice.max_speech_chars'),
        ];
    }
}
