<?php

namespace App\Services;

use App\Contracts\AiClientContract;
use App\Enums\AiProvider;
use App\Models\AiSystem;

class AiClientFactory
{
    /**
     * Create a provider client instance configured from an AiSystem model.
     */
    public function forSystem(AiSystem $system): AiClientContract
    {
        $provider = AiProvider::tryFrom($system->provider);

        if ($provider === null) {
            throw new \RuntimeException("Unsupported AI provider: {$system->provider}");
        }

        $client = match ($provider) {
            AiProvider::Anthropic => new ClaudeService(
                apiKey: $system->api_key,
                model: $system->model,
                maxTokens: $system->max_tokens,
                apiVersion: $system->api_version,
                baseUrl: $system->base_url,
            ),
            AiProvider::OpenAI, AiProvider::OpenAICompatible => new OpenAiService(
                apiKey: $system->api_key,
                model: $system->model,
                maxTokens: $system->max_tokens,
                baseUrl: $system->base_url,
            ),
        };

        if ($system->temperature !== null) {
            $client->withTemperature((float) $system->temperature);
        }

        return $client;
    }

    /**
     * Create a provider client for the default system assigned to a feature.
     */
    public function forFeature(string $feature): AiClientContract
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
