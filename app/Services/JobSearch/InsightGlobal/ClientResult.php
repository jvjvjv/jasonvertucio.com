<?php

namespace App\Services\JobSearch\InsightGlobal;

/**
 * The outcome of one Insight Global request: decoded JSON, a failure with its
 * status or reason, or a request the budget refused to send.
 */
final readonly class ClientResult
{
    /**
     * @param  array<string, mixed>|null  $data
     */
    private function __construct(
        public ?array $data,
        public ?string $reason = null,
        public ?int $status = null,
        public bool $budgetExhausted = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function ok(array $data): self
    {
        return new self($data);
    }

    public static function failure(string $reason, ?int $status = null): self
    {
        return new self(null, $reason, $status);
    }

    public static function budgetExhausted(): self
    {
        return new self(null, 'not sent: the request budget for this call was used up', null, true);
    }

    public function failed(): bool
    {
        return $this->data === null;
    }

    /**
     * @return array{status?: int, reason: string}
     */
    public function errorDetail(): array
    {
        return array_filter(
            ['status' => $this->status, 'reason' => (string) $this->reason],
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
