<?php

return [
    'user_model' => \App\Models\User::class,

    'middleware' => [
        'web',
        \App\Http\Middleware\HandleChatInertiaRequests::class,
    ],

    'admin_middleware' => [
        'web',
        'auth',
        'can:manage-ai-tools',
        \App\Http\Middleware\HandleInertiaRequests::class,
    ],

    'reserved_slugs' => [
        '_boost',
        'mlopnadjs22tn',
        'paper',
        'passkey',
        'resume',
        'wp-admin',
        'wp-login.php',
    ],

    'schedule' => true,

    'providers' => [

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
            'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 1024),
            'api_version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
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
                    'claude-sonnet-4-6' => [
                        'input_per_million' => (float) env('ANTHROPIC_SONNET_46_INPUT_PER_MILLION', 3.00),
                        'output_per_million' => (float) env('ANTHROPIC_SONNET_46_OUTPUT_PER_MILLION', 15.00),
                    ],
                    'claude-opus-4' => [
                        'input_per_million' => (float) env('ANTHROPIC_OPUS_4_INPUT_PER_MILLION', 15.00),
                        'output_per_million' => (float) env('ANTHROPIC_OPUS_4_OUTPUT_PER_MILLION', 75.00),
                    ],
                    'claude-opus-4-5' => [
                        'input_per_million' => (float) env('ANTHROPIC_OPUS_4_INPUT_PER_MILLION', 15.00),
                        'output_per_million' => (float) env('ANTHROPIC_OPUS_4_OUTPUT_PER_MILLION', 75.00),
                    ],
                ],
            ],
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 1024),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'pricing' => [
                'default' => [
                    'input_per_million' => (float) env('OPENAI_DEFAULT_INPUT_PER_MILLION', 0.00),
                    'output_per_million' => (float) env('OPENAI_DEFAULT_OUTPUT_PER_MILLION', 0.00),
                ],
                'models' => [
                    'gpt-4o' => [
                        'input_per_million' => (float) env('OPENAI_GPT_4O_INPUT_PER_MILLION', 5.00),
                        'output_per_million' => (float) env('OPENAI_GPT_4O_OUTPUT_PER_MILLION', 15.00),
                    ],
                    'gpt-4o-mini' => [
                        'input_per_million' => (float) env('OPENAI_GPT_4O_MINI_INPUT_PER_MILLION', 0.15),
                        'output_per_million' => (float) env('OPENAI_GPT_4O_MINI_OUTPUT_PER_MILLION', 0.60),
                    ],
                ],
            ],
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            'max_tokens' => (int) env('GEMINI_MAX_TOKENS', 1024),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],

        'grok' => [
            'model' => env('GROK_MODEL', 'grok-3-mini'),
            'max_tokens' => (int) env('GROK_MAX_TOKENS', 1024),
            'base_url' => env('GROK_BASE_URL', 'https://api.x.ai/v1'),
        ],

        'lm-studio' => [
            'server_url' => env('LMSTUDIO_SERVER_URL', 'http://localhost:1234'),
            'model' => env('LMSTUDIO_MODEL', ''),
            'max_tokens' => (int) env('LMSTUDIO_MAX_TOKENS', 1024),
        ],

    ],
];
