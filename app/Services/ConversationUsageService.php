<?php

namespace App\Services;

use App\Enums\AiInteractionStatus;
use App\Models\AiConversation;
use App\Models\AiInteractionLog;
use Illuminate\Support\Collection;

class ConversationUsageService
{
    public function syncConversation(AiConversation $conversation): bool
    {
        $usage = $this->buildUsageSummary($conversation);

        $updated = [
            'usage_input_tokens' => $usage['input_tokens'],
            'usage_output_tokens' => $usage['output_tokens'],
            'usage_total_tokens' => $usage['total_tokens'],
            'usage_cost_usd' => $usage['cost_usd'],
            'usage_synced_at' => now(),
        ];

        $currentCost = $conversation->usage_cost_usd !== null
            ? (float) $conversation->usage_cost_usd
            : null;

        $hasChanges =
            $conversation->usage_input_tokens !== $updated['usage_input_tokens']
            || $conversation->usage_output_tokens !== $updated['usage_output_tokens']
            || $conversation->usage_total_tokens !== $updated['usage_total_tokens']
            || $currentCost !== $updated['usage_cost_usd'];

        AiConversation::withoutTimestamps(function () use ($conversation, $updated): void {
            $conversation->forceFill($updated)->save();
        });

        return $hasChanges;
    }

    /**
     * @return array{input_tokens: ?int, output_tokens: ?int, total_tokens: ?int, cost_usd: ?float}
     */
    public function buildUsageSummary(AiConversation $conversation): array
    {
        $rows = AiInteractionLog::query()
            ->where('ai_conversation_id', $conversation->id)
            ->where('status', AiInteractionStatus::Success->value)
            ->where(function ($query): void {
                $query->whereNotNull('input_tokens')
                    ->orWhereNotNull('output_tokens');
            })
            ->join('ai_systems', 'ai_systems.id', '=', 'ai_interaction_logs.ai_system_id')
            ->selectRaw('ai_systems.provider as provider, ai_interaction_logs.model as model, COALESCE(SUM(ai_interaction_logs.input_tokens), 0) as input_tokens_sum, COALESCE(SUM(ai_interaction_logs.output_tokens), 0) as output_tokens_sum')
            ->groupBy('ai_systems.provider', 'ai_interaction_logs.model')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'input_tokens' => null,
                'output_tokens' => null,
                'total_tokens' => null,
                'cost_usd' => null,
            ];
        }

        $inputTokens = (int) $rows->sum('input_tokens_sum');
        $outputTokens = (int) $rows->sum('output_tokens_sum');
        $totalTokens = $inputTokens + $outputTokens;
        $costUsd = $this->estimateCostUsd($rows);

        return [
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $totalTokens,
            'cost_usd' => $costUsd,
        ];
    }

    private function estimateCostUsd(Collection $rows): ?float
    {
        $totalCost = 0.0;

        foreach ($rows as $row) {
            $providerPricing = $this->pricingConfigForProvider((string) ($row->provider ?? ''));

            if ($providerPricing === null) {
                return null;
            }

            $pricingByModel = (array) ($providerPricing['models'] ?? []);
            $defaultPricing = (array) ($providerPricing['default'] ?? []);

            $defaultInputRate = isset($defaultPricing['input_per_million']) ? (float) $defaultPricing['input_per_million'] : null;
            $defaultOutputRate = isset($defaultPricing['output_per_million']) ? (float) $defaultPricing['output_per_million'] : null;

            $model = (string) ($row->model ?? '');
            $modelPricing = (array) ($pricingByModel[$model] ?? []);

            $inputRate = isset($modelPricing['input_per_million'])
                ? (float) $modelPricing['input_per_million']
                : $defaultInputRate;
            $outputRate = isset($modelPricing['output_per_million'])
                ? (float) $modelPricing['output_per_million']
                : $defaultOutputRate;

            if ($inputRate === null || $outputRate === null) {
                return null;
            }

            $inputTokens = (int) ($row->input_tokens_sum ?? 0);
            $outputTokens = (int) ($row->output_tokens_sum ?? 0);

            $totalCost += ($inputTokens / 1000000) * $inputRate;
            $totalCost += ($outputTokens / 1000000) * $outputRate;
        }

        return round($totalCost, 6);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pricingConfigForProvider(string $provider): ?array {
        return match ($provider) {
            'anthropic' => (array) config('claude.pricing', []),
            'openai', 'openai-compatible' => (array) config('openai.pricing', []),
            default => null,
        };
    }
}
