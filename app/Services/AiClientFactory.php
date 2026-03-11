<?php

namespace App\Services;

use App\Models\AiSystem;

class AiClientFactory
{
    /**
     * Create a ClaudeService instance configured from an AiSystem model.
     */
    public function forSystem(AiSystem $system): ClaudeService
    {
        $client = new ClaudeService(
            apiKey: $system->api_key,
            model: $system->model,
            maxTokens: $system->max_tokens,
            apiVersion: $system->api_version,
            baseUrl: $system->base_url,
        );

        if ($system->temperature !== null) {
            $client->withTemperature((float) $system->temperature);
        }

        return $client;
    }

    /**
     * Create a ClaudeService for the default system assigned to a feature.
     */
    public function forFeature(string $feature): ClaudeService
    {
        $system = AiSystem::defaultForFeature($feature);

        if ($system === null) {
            throw new \RuntimeException("No default AI system configured for feature: {$feature}");
        }

        if (!$system->is_active) {
            throw new \RuntimeException("The default AI system for feature '{$feature}' is inactive.");
        }

        return $this->forSystem($system);
    }
}
