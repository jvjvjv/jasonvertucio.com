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

        $conversation->forceFill($updated)->save();

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
            ->selectRaw('model, COALESCE(SUM(input_tokens), 0) as input_tokens_sum, COALESCE(SUM(output_tokens), 0) as output_tokens_sum')
            ->groupBy('model')
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
        $pricingByModel = (array) config('claude.pricing.models', []);
        $defaultPricing = (array) config('claude.pricing.default', []);
        $defaultInputRate = isset($defaultPricing['input_per_million']) ? (float) $defaultPricing['input_per_million'] : null;
        $defaultOutputRate = isset($defaultPricing['output_per_million']) ? (float) $defaultPricing['output_per_million'] : null;

        $totalCost = 0.0;

        foreach ($rows as $row) {
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
}
