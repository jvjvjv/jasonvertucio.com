<?php

namespace App\Contracts\Mcp;

interface AiToolHandlerContract
{
    public function name(): string;

    public function description(): string;

    /**
     * @return array<string, mixed>
     */
    public function schema(): array;

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function handle(array $input): array;
}
