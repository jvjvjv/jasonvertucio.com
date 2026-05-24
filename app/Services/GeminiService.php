<?php

namespace App\Services;

use App\Contracts\AiClientContract;
use Generator;
use Illuminate\Support\Facades\Http;

class GeminiService implements AiClientContract
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
        $this->defaultModel = $model ?? config('gemini.model', 'gemini-2.5-flash');
        $this->defaultMaxTokens = $maxTokens ?? (int) config('gemini.max_tokens', 1024);
        $this->baseUrl = rtrim($baseUrl ?? config('gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
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
        $payload = $this->buildPayload($messages);

        $response = Http::withHeaders($this->headers())
            ->timeout(600)
            ->post($this->urlForModel($this->activeModel()) . ':generateContent', $payload + $this->authQuery());

        $this->reset();

        $response->throw();

        $data = $response->json();
        $candidate = $data['candidates'][0] ?? [];
        $reasoning = $this->candidateThoughtText($candidate);

        return [
            'id' => (string) ($data['responseId'] ?? ''),
            'type' => 'message',
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'text',
                    'text' => $this->candidateResponseText($candidate),
                ],
            ],
            'reasoning_content' => $reasoning !== '' ? $reasoning : null,
            'model' => $this->activeModel(),
            'stop_reason' => (string) ($candidate['finishReason'] ?? 'stop'),
            'usage' => [
                'input_tokens' => (int) ($data['usageMetadata']['promptTokenCount'] ?? 0),
                'output_tokens' => (int) ($data['usageMetadata']['candidatesTokenCount'] ?? 0),
            ],
        ];
    }

    public function stream(array $messages): Generator
    {
        $payload = $this->buildPayload($messages);

        $response = Http::withHeaders($this->headers())
            ->withOptions(['stream' => true])
            ->timeout(600)
            ->post($this->urlForModel($this->activeModel()) . ':streamGenerateContent', $payload + ['alt' => 'sse'] + $this->authQuery());

        $this->reset();

        $response->throw();

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $started = false;
        $outputTokens = null;

        while (!$body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '' || !str_starts_with($line, 'data: ')) {
                    continue;
                }

                $chunk = json_decode(substr($line, 6), true);

                if (!is_array($chunk)) {
                    continue;
                }

                if (!$started) {
                    $started = true;
                    yield [
                        'type' => 'message_start',
                        'message' => [
                            'usage' => [
                                'input_tokens' => (int) ($chunk['usageMetadata']['promptTokenCount'] ?? 0),
                            ],
                        ],
                    ];
                }

                $candidate = $chunk['candidates'][0] ?? [];
                $reasoning = $this->candidateThoughtText($candidate);
                $text = $this->candidateResponseText($candidate);

                if ($reasoning !== '') {
                    yield [
                        'type' => 'reasoning_block_delta',
                        'delta' => [
                            'reasoning' => $reasoning,
                        ],
                    ];
                }

                if ($text !== '') {
                    yield [
                        'type' => 'content_block_delta',
                        'delta' => [
                            'text' => $text,
                        ],
                    ];
                }

                if (isset($chunk['usageMetadata']['candidatesTokenCount'])) {
                    $outputTokens = (int) $chunk['usageMetadata']['candidatesTokenCount'];
                }

                if (isset($candidate['finishReason']) && $candidate['finishReason'] !== null) {
                    $stopReason = match ($candidate['finishReason']) {
                        'MAX_TOKENS' => 'max_tokens',
                        default => 'end_turn',
                    };

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
            }
        }

        if ($started === false) {
            yield [
                'type' => 'message_start',
                'message' => [
                    'usage' => [
                        'input_tokens' => 0,
                    ],
                ],
            ];
        }

        yield [
            'type' => 'message_delta',
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
            ->get($this->baseUrl . '/models', $this->authQuery());

        $response->throw();

        $models = $response->json('models', []);

        if (!is_array($models)) {
            return [];
        }

        return collect($models)
            ->filter(static fn (mixed $model): bool => is_array($model) && isset($model['name']))
            ->map(static function (array $model): array {
                $name = (string) ($model['name'] ?? '');
                $modelId = str_starts_with($name, 'models/') ? substr($name, 7) : $name;

                return [
                    'id' => $modelId,
                    'display_name' => (string) ($model['displayName'] ?? $modelId),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * @param array<int, array{id: string, name: string, input: array<string, mixed>}> $toolCalls
     * @return array{role: string, parts: array<int, mixed>}
     */
    public function formatAssistantToolCallTurn(string $textContent, array $toolCalls): array
    {
        $parts = [];

        if ($textContent !== '') {
            $parts[] = ['text' => $textContent];
        }

        foreach ($toolCalls as $toolCall) {
            $parts[] = [
                'functionCall' => [
                    'name' => $toolCall['name'],
                    'args' => $toolCall['input'],
                ],
            ];
        }

        return ['role' => 'model', 'parts' => $parts];
    }

    /**
     * @param array<int, array{id: string, result: array<string, mixed>}> $toolResults
     * @return array<int, array{role: string, parts: array<int, mixed>}>
     */
    public function formatToolResultTurn(array $toolResults): array
    {
        $parts = array_map(static fn (array $result): array => [
            'functionResponse' => [
                'name' => $result['name'] ?? $result['id'],
                'response' => $result['result'],
            ],
        ], $toolResults);

        return [['role' => 'user', 'parts' => $parts]];
    }

    /**
     * @param array<int, array{role: string, content: string|array<int, mixed>}> $messages
     * @return array<string, mixed>
     */
    private function buildPayload(array $messages): array
    {
        $payload = [
            'contents' => $this->mapMessages($messages),
            'generationConfig' => [
                'maxOutputTokens' => $this->maxTokens ?? $this->defaultMaxTokens,
            ],
        ];

        if ($this->system !== null && $this->system !== '') {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $this->system],
                ],
            ];
        }

        if ($this->temperature !== null) {
            $payload['generationConfig']['temperature'] = $this->temperature;
        }

        return $payload;
    }

    /**
     * @param array<int, array{role: string, content: string|array<int, mixed>}> $messages
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    private function mapMessages(array $messages): array
    {
        return collect($messages)
            ->map(function (array $message): array {
                $role = $message['role'] === 'assistant' ? 'model' : 'user';
                $content = $message['content'];
                $text = is_string($content) ? $content : (json_encode($content, JSON_UNESCAPED_SLASHES) ?: '');

                return [
                    'role' => $role,
                    'parts' => [
                        ['text' => $text],
                    ],
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function candidateResponseText(array $candidate): string
    {
        $parts = $candidate['content']['parts'] ?? [];

        if (!is_array($parts)) {
            return '';
        }

        return collect($parts)
            ->filter(static fn (mixed $part): bool => is_array($part) && empty($part['thought']))
            ->map(static fn (mixed $part): string => is_array($part) ? (string) ($part['text'] ?? '') : '')
            ->implode('');
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function candidateThoughtText(array $candidate): string
    {
        $parts = $candidate['content']['parts'] ?? [];

        if (!is_array($parts)) {
            return '';
        }

        return collect($parts)
            ->filter(static fn (mixed $part): bool => is_array($part) && !empty($part['thought']))
            ->map(static fn (mixed $part): string => is_array($part) ? (string) ($part['text'] ?? '') : '')
            ->implode('');
    }

    private function activeModel(): string
    {
        return $this->model ?? $this->defaultModel;
    }

    private function urlForModel(string $model): string
    {
        return $this->baseUrl . '/models/' . $model;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'content-type' => 'application/json',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function authQuery(): array
    {
        return [
            'key' => $this->apiKey,
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
