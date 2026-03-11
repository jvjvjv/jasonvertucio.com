<?php

namespace App\Services;

use Generator;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class ClaudeService
{
    private string $apiKey;
    private string $defaultModel;
    private int $defaultMaxTokens;
    private string $apiVersion;
    private string $baseUrl;

    /** @var string|null Per-request system prompt */
    private ?string $system = null;

    /** @var string|null Per-request model override */
    private ?string $model = null;

    /** @var int|null Per-request max_tokens override */
    private ?int $maxTokens = null;

    /** @var float|null Per-request temperature override */
    private ?float $temperature = null;

    /** @var array<int, array{name: string, description: string, input_schema: array<string, mixed>}> Per-request tools */
    private array $tools = [];

    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        ?int $maxTokens = null,
        ?string $apiVersion = null,
        ?string $baseUrl = null,
    ) {
        $this->apiKey = $apiKey ?? config('claude.api_key', '');
        $this->defaultModel = $model ?? config('claude.model', 'claude-sonnet-4-6');
        $this->defaultMaxTokens = $maxTokens ?? (int) config('claude.max_tokens', 1024);
        $this->apiVersion = $apiVersion ?? config('claude.api_version', '2023-06-01');
        $this->baseUrl = $baseUrl ?? config('claude.base_url', 'https://api.anthropic.com/v1');
    }

    /**
     * Set a system prompt for the next request.
     */
    public function withSystem(string $system): self
    {
        $this->system = $system;

        return $this;
    }

    /**
     * Override the model for the next request.
     */
    public function withModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Override max tokens for the next request.
     */
    public function withMaxTokens(int $maxTokens): self
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    /**
     * Set the temperature for the next request (0.0 to 1.0).
     */
    public function withTemperature(float $temperature): self
    {
        $this->temperature = $temperature;

        return $this;
    }

    /**
     * Attach tools for the next request.
     *
     * Each tool should have: name, description, and input_schema.
     *
     * @param array<int, array{name: string, description: string, input_schema: array<string, mixed>}> $tools
     */
    public function withTools(array $tools): self
    {
        $this->tools = $tools;

        return $this;
    }

    /**
     * Send a message and return the parsed response.
     *
     * @param array<int, array{role: string, content: string|array<int, mixed>}> $messages
     * @return array{id: string, type: string, role: string, content: array<int, mixed>, model: string, stop_reason: string, usage: array{input_tokens: int, output_tokens: int}}
     *
     * @throws RequestException
     */
    public function message(array $messages): array
    {
        $payload = $this->buildPayload($messages);

        $response = Http::withHeaders($this->headers())
            ->timeout(120)
            ->post($this->baseUrl . '/messages', $payload);

        $this->reset();

        $response->throw();

        return $response->json();
    }

    /**
     * Send a message with streaming enabled.
     *
     * Yields decoded SSE event arrays. Use the 'type' key to distinguish
     * event types (e.g., 'content_block_delta', 'message_stop').
     *
     * @param array<int, array{role: string, content: string|array<int, mixed>}> $messages
     * @return Generator<int, array<string, mixed>>
     *
     * @throws RequestException
     */
    public function stream(array $messages): Generator
    {
        $payload = $this->buildPayload($messages, streaming: true);

        $response = Http::withHeaders($this->headers())
            ->withOptions(['stream' => true])
            ->timeout(120)
            ->post($this->baseUrl . '/messages', $payload);

        $this->reset();

        $response->throw();

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                if (str_starts_with($line, 'data: ')) {
                    $data = json_decode(substr($line, 6), true);

                    if ($data !== null) {
                        yield $data;
                    }
                }
            }
        }
    }

    /**
     * Build the request payload from current state.
     *
     * @param array<int, array{role: string, content: string|array<int, mixed>}> $messages
     */
    private function buildPayload(array $messages, bool $streaming = false): array
    {
        $payload = [
            'model' => $this->model ?? $this->defaultModel,
            'max_tokens' => $this->maxTokens ?? $this->defaultMaxTokens,
            'messages' => $messages,
        ];

        if ($this->system !== null) {
            $payload['system'] = $this->system;
        }

        if ($this->tools !== []) {
            $payload['tools'] = $this->tools;
        }

        if ($this->temperature !== null) {
            $payload['temperature'] = $this->temperature;
        }

        if ($streaming) {
            $payload['stream'] = true;
        }

        return $payload;
    }

    /**
     * Get configured HTTP headers for the Anthropic API.
     *
     * @return array{x-api-key: string, anthropic-version: string, content-type: string}
     */
    private function headers(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->apiVersion,
            'content-type' => 'application/json',
        ];
    }

    /**
     * Reset per-request overrides back to defaults.
     */
    private function reset(): void
    {
        $this->system = null;
        $this->model = null;
        $this->maxTokens = null;
        $this->temperature = null;
        $this->tools = [];
    }
}
