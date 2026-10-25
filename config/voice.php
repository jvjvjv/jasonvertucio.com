<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dictation
    |--------------------------------------------------------------------------
    |
    | The largest recording the transcription endpoint accepts, in kilobytes,
    | and how long the composer records before stopping on its own. The cap
    | keeps a clip inside the speech server's request timeout.
    |
    | `transcription_language` is sent with every transcription that does not
    | name its own language. Blank means none is sent and the speech server
    | detects the language itself.
    |
    */

    'max_upload_kb' => (int) env('VOICE_MAX_UPLOAD_KB', 10240),

    'max_recording_seconds' => (int) env('VOICE_MAX_RECORDING_SECONDS', 120),

    'transcription_language' => env('VOICE_TRANSCRIPTION_LANGUAGE', ''),

    /*
    |--------------------------------------------------------------------------
    | Speech
    |--------------------------------------------------------------------------
    |
    | The longest text, after Markdown is removed, that one speech request may
    | carry. Documents are read one paragraph per request, so this bounds a
    | paragraph, not a document.
    |
    */

    'max_speech_chars' => (int) env('VOICE_MAX_SPEECH_CHARS', 4000),

    /*
    |--------------------------------------------------------------------------
    | Speech Cache
    |--------------------------------------------------------------------------
    |
    | Generated speech is kept on the local disk under `voice-cache/` and
    | removed by `voice:sweep-cache` once older than this many days.
    |
    */

    'cache_ttl_days' => (int) env('VOICE_CACHE_TTL_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Status Probe
    |--------------------------------------------------------------------------
    |
    | How long the status check waits for a speech server to answer, in
    | seconds, and how long the answer is remembered so that page loads do not
    | each probe the server.
    |
    */

    'status_probe_timeout' => (int) env('VOICE_STATUS_PROBE_TIMEOUT', 2),

    'status_cache_seconds' => (int) env('VOICE_STATUS_CACHE_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Requests per minute per user. Voice is for one person, so these stop a
    | runaway client rather than shape normal use.
    |
    */

    'rate_limits' => [
        'transcribe_per_minute' => (int) env('VOICE_TRANSCRIBE_PER_MINUTE', 30),
        'speech_per_minute' => (int) env('VOICE_SPEECH_PER_MINUTE', 120),
    ],

];
