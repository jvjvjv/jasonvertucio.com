<?php

return [
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
            'gpt-4.1' => [
                'input_per_million' => (float) env('OPENAI_GPT_41_INPUT_PER_MILLION', 2.00),
                'output_per_million' => (float) env('OPENAI_GPT_41_OUTPUT_PER_MILLION', 8.00),
            ],
            'gpt-4.1-mini' => [
                'input_per_million' => (float) env('OPENAI_GPT_41_MINI_INPUT_PER_MILLION', 0.40),
                'output_per_million' => (float) env('OPENAI_GPT_41_MINI_OUTPUT_PER_MILLION', 1.60),
            ],
        ],
    ],
];
