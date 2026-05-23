<?php

namespace App\Services;

use App\Contracts\AiClientContract;
use Generator;
use Illuminate\Support\Facades\Http;

class OpenAiService implements AiClientContract
{
    private string $apiKey;
    private string $defaultModel;
    private int $defaultMaxTokens;
    private string $baseUrl;

    private ?string $system = null;
    private ?string $model = null;
    private ?int $maxTokens = null;
    private ?float $temperature = null;

    /** @var array<int, array{name: string, description: string, input_schema: array<string, mixed>}> */
    private array $tools = [];

    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        ?int $maxTokens = null,
        ?string $baseUrl = null,
    ) {
        $this->apiKey = $apiKey ?? '';
        $this->defaultModel = $model ?? config('openai.model', 'gpt-4o-mini');
        $this->defaultMaxTokens = $maxTokens ?? (int) config('openai.max_tokens', 1024);
        $this->baseUrl = rtrim($baseUrl ?? config('openai.base_url', 'https://api.openai.com/v1'), '/');
    }

    public function withSystem(string $system): self
    {
        $this->system = $system;

        return $this;
    }

    public function withModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function withMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    public function withTemperature(float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function withTools(array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    public function message(array $messages): array
    {
        $payload = $this->buildPayload($messages, false);

        $response = Http::withHeaders($this->headers())
            ->timeout(600)
            ->post($this->baseUrl . '/chat/completions', $payload);

        $this->reset();

        $response->throw();

        $data = $response->json();
        $choice = $data['choices'][0] ?? [];
        $content = $choice['message']['content'] ?? '';

        if (is_array($content)) {
            $content = collect($content)
                ->map(static fn (array $part): string => (string) ($part['text'] ?? ''))
                ->implode('');
        }

        return [
            'id' => (string) ($data['id'] ?? ''),
            'type' => 'message',
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'text',
                    'text' => (string) $content,
                ],
            ],
            'reasoning_content' => $choice['message']['reasoning_content'] ?? null,
            'model' => (string) ($data['model'] ?? ($this->model ?? $this->defaultModel)),
            'stop_reason' => (string) ($choice['finish_reason'] ?? 'stop'),
            'usage' => [
                'input_tokens' => (int) ($data['usage']['prompt_tokens'] ?? 0),
                'output_tokens' => (int) ($data['usage']['completion_tokens'] ?? 0),
            ],
        ];
    }

    public function stream(array $messages): Generator
    {
        $payload = $this->buildPayload($messages, true);

        $response = Http::withHeaders($this->headers())
            ->withOptions(['stream' => true])
            ->timeout(600)
            ->post($this->baseUrl . '/chat/completions', $payload);

        $this->reset();

        $response->throw();

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $inputTokens = null;
        $outputTokens = null;
        $started = false;

        // Track in-flight tool calls: index → {id, name, arguments}
        /** @var array<int, array{id: string, name: string, arguments: string}> $pendingToolCalls */
        $pendingToolCalls = [];
        $finishReason = null;

        while (!$body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '' || !str_starts_with($line, 'data: ')) {
                    continue;
                }

                $rawData = substr($line, 6);

                if ($rawData === '[DONE]') {
                    break 2;
                }

                $chunk = json_decode($rawData, true);

                if (!is_array($chunk)) {
                    continue;
                }

                if (isset($chunk['usage'])) {
                    $inputTokens = isset($chunk['usage']['prompt_tokens']) ? (int) $chunk['usage']['prompt_tokens'] : $inputTokens;
                    $outputTokens = isset($chunk['usage']['completion_tokens']) ? (int) $chunk['usage']['completion_tokens'] : $outputTokens;
                }

                if (!$started) {
                    $started = true;

                    yield [
                        'type' => 'message_start',
                        'message' => [
                            'usage' => [
                                'input_tokens' => $inputTokens,
                            ],
                        ],
                    ];
                }

                $choice = $chunk['choices'][0] ?? [];
                $delta = $choice['delta'] ?? [];
                $text = $delta['content'] ?? null;
                $reasoning = $delta['reasoning_content'] ?? null;

                if (is_string($reasoning) && $reasoning !== '') {
                    yield [
                        'type' => 'reasoning_block_delta',
                        'delta' => [
                            'reasoning' => $reasoning,
                        ],
                    ];
                }

                if (is_string($text) && $text !== '') {
                    yield [
                        'type' => 'content_block_delta',
                        'delta' => [
                            'text' => $text,
                        ],
                    ];
                }

                // Accumulate streaming tool_calls fragments
                if (!empty($delta['tool_calls'])) {
                    foreach ($delta['tool_calls'] as $tcDelta) {
                        $idx = (int) ($tcDelta['index'] ?? 0);

                        if (!isset($pendingToolCalls[$idx])) {
                            $pendingToolCalls[$idx] = ['id' => '', 'name' => '', 'arguments' => ''];
                        }

                        if (isset($tcDelta['id']) && $tcDelta['id'] !== '') {
                            $pendingToolCalls[$idx]['id'] = $tcDelta['id'];
                        }

                        if (isset($tcDelta['function']['name']) && $tcDelta['function']['name'] !== '') {
                            $pendingToolCalls[$idx]['name'] = $tcDelta['function']['name'];
                        }

                        if (isset($tcDelta['function']['arguments'])) {
                            $pendingToolCalls[$idx]['arguments'] .= $tcDelta['function']['arguments'];
                        }
                    }
                }

                if (isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
                    $finishReason = $choice['finish_reason'];
                }
            }
        }

        // Emit accumulated tool calls as normalized Anthropic-style events
        foreach ($pendingToolCalls as $toolCall) {
            yield [
                'type' => 'content_block_start',
                'content_block' => [
                    'type' => 'tool_use',
                    'id' => $toolCall['id'],
                    'name' => $toolCall['name'],
                ],
            ];

            yield [
                'type' => 'content_block_delta',
                'delta' => [
                    'type' => 'input_json_delta',
                    'partial_json' => $toolCall['arguments'],
                ],
            ];

            yield ['type' => 'content_block_stop'];
        }

        if ($started === false) {
            yield [
                'type' => 'message_start',
                'message' => [
                    'usage' => [
                        'input_tokens' => $inputTokens,
                    ],
                ],
            ];
        }

        $stopReason = $finishReason === 'tool_calls' ? 'tool_use' : 'end_turn';

        yield [
            'type' => 'message_delta',
            'delta' => ['stop_reason' => $stopReason],
            'usage' => [
                'output_tokens' => $outputTokens,
            ],
        ];

        yield [
            'type' => 'message_stop',
        ];
    }

    public function listModels(): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout(15)
            ->get($this->baseUrl . '/models');

        $response->throw();

        $models = $response->json('data', []);

        if (!is_array($models)) {
            return [];
        }

        return collect($models)
            ->filter(static fn (mixed $model): bool => is_array($model) && isset($model['id']))
            ->map(static fn (array $model): array => [
                'id' => (string) $model['id'],
                'display_name' => (string) ($model['id'] ?? ''),
            ])
            ->values()
            ->toArray();
    }

    /**
     * @param array<int, array{id: string, name: string, input: array<string, mixed>}> $toolCalls
     * @return array{role: string, content: string|null, tool_calls: array<int, mixed>}
     */
    public function formatAssistantToolCallTurn(string $textContent, array $toolCalls): array
    {
        $formattedCalls = [];

        foreach ($toolCalls as $toolCall) {
            $formattedCalls[] = [
                'id' => $toolCall['id'],
                'type' => 'function',
                'function' => [
                    'name' => $toolCall['name'],
                    'arguments' => json_encode(
                        $toolCall['input'] === [] ? new \stdClass() : $toolCall['input']
                    ),
                ],
            ];
        }

        return [
            'role' => 'assistant',
            'content' => $textContent !== '' ? $textContent : null,
            'tool_calls' => $formattedCalls,
        ];
    }

    /**
     * @param array<int, array{id: string, result: array<string, mixed>}> $toolResults
     * @return array<int, array{role: string, tool_call_id: string, content: string}>
     */
    public function formatToolResultTurn(array $toolResults): array
    {
        return array_map(static fn (array $result): array => [
            'role' => 'tool',
            'tool_call_id' => $result['tool_use_id'] ?? $result['id'],
            'content' => json_encode($result['result']),
        ], $toolResults);
    }

    /**
     * @param array<int, array{role: string, content: string|array<int, mixed>}> $messages
     */
    private function buildPayload(array $messages, bool $streaming): array
    {
        $payload = [
            'model' => $this->model ?? $this->defaultModel,
            'max_tokens' => $this->maxTokens ?? $this->defaultMaxTokens,
            'messages' => $this->buildMessages($messages),
        ];

        if ($this->temperature !== null) {
            $payload['temperature'] = $this->temperature;
        }

        if ($streaming) {
            $payload['stream'] = true;
            $payload['stream_options'] = ['include_usage' => true];
        }

        if ($this->tools !== []) {
            $payload['tools'] = collect($this->tools)
                ->map(static fn (array $tool): array => [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => $tool['description'],
                        'parameters' => $tool['input_schema'],
                    ],
                ])
                ->values()
                ->toArray();
        }

        return $payload;
    }

    /**
     * @param array<int, array{role: string, content: string|array<int, mixed>}> $messages
     * @return array<int, array{role: string, content: string|array<int, mixed>}>
     */
    private function buildMessages(array $messages): array
    {
        if ($this->system === null || $this->system === '') {
            return $messages;
        }

        return [
            ['role' => 'system', 'content' => $this->system],
            ...$messages,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        if ($this->apiKey === '') {
            return ['content-type' => 'application/json'];
        }

        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'content-type' => 'application/json',
        ];
    }

    private function reset(): void
    {
        $this->system = null;
        $this->model = null;
        $this->maxTokens = null;
        $this->temperature = null;
        $this->tools = [];
    }
}
