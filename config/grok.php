<?php

return [
    'model' => env('GROK_MODEL', 'grok-3-mini'),
    'max_tokens' => (int) env('GROK_MAX_TOKENS', 1024),
    'base_url' => env('GROK_BASE_URL', 'https://api.x.ai/v1'),
];
