<?php

namespace App\Contracts;

use Generator;

interface AiClientContract
{
    public function withSystem(string $system): self;

    public function withModel(string $model): self;

    public function withMaxTokens(int $maxTokens): self;

    public function withTemperature(float $temperature): self;

    /**
     * @param array<int, array{name: string, description: string, input_schema: array<string, mixed>}> $tools
     */
    public function withTools(array $tools): self;

    /**
     * @param array<int, array{role: string, content: string|array<int, mixed>}> $messages
     * @return array<string, mixed>
     */
    public function message(array $messages): array;

    /**
     * @param array<int, array{role: string, content: string|array<int, mixed>}> $messages
     * @return Generator<int, array<string, mixed>>
     */
    public function stream(array $messages): Generator;

    /**
     * @return array<int, array{id: string, display_name: string}>
     */
    public function listModels(): array;
}
