<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Anthropic API Key
    |--------------------------------------------------------------------------
    */

    'api_key' => env('ANTHROPIC_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default Model
    |--------------------------------------------------------------------------
    */

    'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),

    /*
    |--------------------------------------------------------------------------
    | Default Max Tokens
    |--------------------------------------------------------------------------
    */

    'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 1024),

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    */

    'api_version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    */

    'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Estimated Token Pricing (USD per 1M tokens)
    |--------------------------------------------------------------------------
    |
    | These values are used for per-conversation usage estimates. Override via
    | environment variables when provider pricing changes.
    |
    */

    'pricing' => [
        'default' => [
            'input_per_million' => (float) env('ANTHROPIC_DEFAULT_INPUT_PER_MILLION', 3.00),
            'output_per_million' => (float) env('ANTHROPIC_DEFAULT_OUTPUT_PER_MILLION', 15.00),
        ],
        'models' => [
            'claude-haiku-3-5' => [
                'input_per_million' => (float) env('ANTHROPIC_HAIKU_35_INPUT_PER_MILLION', 0.80),
                'output_per_million' => (float) env('ANTHROPIC_HAIKU_35_OUTPUT_PER_MILLION', 4.00),
            ],
            'claude-sonnet-3-7' => [
                'input_per_million' => (float) env('ANTHROPIC_SONNET_37_INPUT_PER_MILLION', 3.00),
                'output_per_million' => (float) env('ANTHROPIC_SONNET_37_OUTPUT_PER_MILLION', 15.00),
            ],
            'claude-sonnet-4' => [
                'input_per_million' => (float) env('ANTHROPIC_SONNET_4_INPUT_PER_MILLION', 3.00),
                'output_per_million' => (float) env('ANTHROPIC_SONNET_4_OUTPUT_PER_MILLION', 15.00),
            ],
            'claude-sonnet-4-5' => [
                'input_per_million' => (float) env('ANTHROPIC_SONNET_45_INPUT_PER_MILLION', 3.00),
                'output_per_million' => (float) env('ANTHROPIC_SONNET_45_OUTPUT_PER_MILLION', 15.00),
            ],
            'claude-sonnet-4-6' => [
                'input_per_million' => (float) env('ANTHROPIC_SONNET_46_INPUT_PER_MILLION', 3.00),
                'output_per_million' => (float) env('ANTHROPIC_SONNET_46_OUTPUT_PER_MILLION', 15.00),
            ],
            'claude-opus-4' => [
                'input_per_million' => (float) env('ANTHROPIC_OPUS_4_INPUT_PER_MILLION', 15.00),
                'output_per_million' => (float) env('ANTHROPIC_OPUS_4_OUTPUT_PER_MILLION', 75.00),
            ],
            'claude-opus-4-1' => [
                'input_per_million' => (float) env('ANTHROPIC_OPUS_41_INPUT_PER_MILLION', 15.00),
                'output_per_million' => (float) env('ANTHROPIC_OPUS_41_OUTPUT_PER_MILLION', 75.00),
            ],
            'claude-opus-4-6' => [
                'input_per_million' => (float) env('ANTHROPIC_OPUS_46_INPUT_PER_MILLION', 15.00),
                'output_per_million' => (float) env('ANTHROPIC_OPUS_46_OUTPUT_PER_MILLION', 75.00),
            ],
            'claude-opus-4-7' => [
                'input_per_million' => (float) env('ANTHROPIC_OPUS_47_INPUT_PER_MILLION', 15.00),
                'output_per_million' => (float) env('ANTHROPIC_OPUS_47_OUTPUT_PER_MILLION', 75.00),
            ],
        ],
    ],

];
