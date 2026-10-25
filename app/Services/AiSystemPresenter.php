<?php

namespace App\Services;

use Jvjvjv\CodeTalker\Enums\AiSystemRole;
use Jvjvjv\CodeTalker\Models\AiSystem;

/**
 * The one place an AiSystem becomes something a browser may receive.
 *
 * An explicit allowlist rather than `makeHidden()`: a column added to the
 * package model later stays out of the page until it is listed here. Stored
 * secrets — the provider key, service credentials and web-tool header values —
 * are reduced to booleans and never leave the server.
 */
class AiSystemPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(AiSystem $system): array
    {
        return [
            'id' => $system->id,
            'name' => $system->name,
            'provider' => $system->provider,
            'role' => ($system->role ?? AiSystemRole::Chat)->value,
            'model' => $system->model,
            'model_capabilities' => $system->model_capabilities,
            'base_url' => $system->base_url,
            'api_version' => $system->api_version,
            'max_tokens' => $system->max_tokens,
            'context_length' => $system->context_length,
            'temperature' => $system->temperature,
            'config' => $system->config,
            'auth_type' => $system->auth_type,
            'endpoint_type' => $system->endpoint_type,
            'stream_protocol' => $system->stream_protocol,
            'system_prompt_id' => $system->system_prompt_id,
            'system_prompt_mode' => $system->system_prompt_mode,
            'supports_tools' => $system->supports_tools,
            'allowed_tools' => $system->allowed_tools,
            'supports_json_mode' => $system->supports_json_mode,
            'enable_thinking' => $system->enable_thinking,
            'is_local_endpoint' => $system->is_local_endpoint,
            'pricing_profile' => $system->pricing_profile,
            'is_active' => $system->is_active,
            'duplicated_at' => $system->duplicated_at,
            'created_at' => $system->created_at,
            'updated_at' => $system->updated_at,
            'interaction_logs_count' => $system->interaction_logs_count ?? null,
            'chat_bots_count' => $system->chat_bots_count ?? null,
            'feature_defaults_list' => $system->feature_defaults_list ?? [],
            'has_api_key' => filled($system->api_key),
            'has_credentials' => filled($system->getRawOriginal('credentials')),
            'web_tool_policy' => $this->presentPolicy($system->web_tool_policy),
        ];
    }

    /**
     * @return array{allowed_domains: array<int, string>, has_headers: array<string, bool>}|null
     */
    private function presentPolicy(mixed $policy): ?array
    {
        if (! is_array($policy) || $policy === []) {
            return null;
        }

        $credentials = is_array($policy['credentials'] ?? null) ? $policy['credentials'] : [];
        $domains = array_values(array_filter($policy['allowed_domains'] ?? [], 'is_string'));

        $hasHeaders = [];
        foreach ($domains as $domain) {
            $hasHeaders[$domain] = is_array($credentials[$domain] ?? null) && $credentials[$domain] !== [];
        }

        return [
            'allowed_domains' => $domains,
            'has_headers' => $hasHeaders,
        ];
    }
}
