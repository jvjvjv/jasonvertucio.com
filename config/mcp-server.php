<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Applied by the `mcp` rate limiter to the public endpoint. Anonymous
    | callers are keyed on their Cloudflare-aware client address; token holders
    | on the presented token, which is hand-issued and individually revocable.
    |
    | Each tier carries a burst window and a sustained window. An ordinary
    | session spends about five requests — initialize, tools/list, and the tool
    | calls themselves — so the burst allowance is several complete sessions.
    |
    */

    'limits' => [

        'anonymous' => [
            'per_minute' => (int) env('MCP_ANON_PER_MINUTE', 30),
            'per_hour' => (int) env('MCP_ANON_PER_HOUR', 200),
        ],

        'token' => [
            'per_minute' => (int) env('MCP_TOKEN_PER_MINUTE', 120),
            'per_day' => (int) env('MCP_TOKEN_PER_DAY', 10000),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Result Cache
    |--------------------------------------------------------------------------
    |
    | Tool results are cached because the underlying data changes on the order
    | of weeks. Entries are invalidated on the events that actually change the
    | data, so this TTL is a backstop rather than the primary freshness
    | mechanism.
    |
    */

    'cache_ttl' => (int) env('MCP_CACHE_TTL', 900),

    /*
    |--------------------------------------------------------------------------
    | Call Log Retention
    |--------------------------------------------------------------------------
    |
    | How many days of call records to keep. The endpoint is public, so this
    | table grows with untrusted traffic and is swept on a schedule.
    |
    */

    'log_retention_days' => (int) env('MCP_LOG_RETENTION_DAYS', 90),

];
