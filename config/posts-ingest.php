<?php

return [

    /*
    |--------------------------------------------------------------------------
    | /posts ingest limits
    |--------------------------------------------------------------------------
    |
    | The endpoint accepts unauthenticated, CSRF-exempt POSTs and writes them to
    | the `posts` log, so every dimension of what it will accept is bounded.
    |
    */

    'rate_limit_per_minute' => (int) env('POSTS_INGEST_RATE_LIMIT_PER_MINUTE', 30),

    // Requests with a larger body are refused (413) before anything is logged.
    'max_body_bytes' => (int) env('POSTS_INGEST_MAX_BODY_BYTES', 16 * 1024),

    // A logged payload longer than this is truncated and marked as such.
    'max_entry_bytes' => (int) env('POSTS_INGEST_MAX_ENTRY_BYTES', 4 * 1024),

];
