<?php

namespace App\Http\Requests\Concerns;

use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Jvjvjv\CodeTalker\Enums\AiProvider;
use Jvjvjv\CodeTalker\Enums\AiSystemRole;

/**
 * The role-aware parts of AiSystem validation, shared by the create and update
 * requests. Which provider can serve which role is the package's decision
 * (`AiProvider::supportsRole()`); this only applies it, because the host
 * validates AiSystem writes itself rather than through `AiSystemManager`.
 *
 * Both requests merge a `role` into the input before validation, so the
 * `exclude_unless:role,chat` rules always have a role to read.
 */
trait ValidatesAiSystemRole
{
    /**
     * Each feature a system can be the default for, and the role it needs.
     *
     * @var array<string, AiSystemRole>
     */
    private const array FEATURE_ROLES = [
        'targeted-resume' => AiSystemRole::Chat,
        'cover-letter' => AiSystemRole::Chat,
        'company-intel' => AiSystemRole::Chat,
        'voice:dictation' => AiSystemRole::Transcription,
        'voice:speech' => AiSystemRole::Audio,
    ];

    /**
     * Fields that only shape a chat turn. A voice system neither needs nor
     * stores them, so they are dropped from its validated data.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function chatOnlyRules(Closure $webToolPolicyRule): array
    {
        $chatOnly = static fn (array $rules): array => ['exclude_unless:role,chat', ...$rules];

        return [
            'model_capabilities' => $chatOnly(['nullable', 'array']),
            'model_capabilities.reasoning' => $chatOnly(['nullable', 'boolean']),
            'model_capabilities.vision' => $chatOnly(['nullable', 'boolean']),
            'model_capabilities.tools' => $chatOnly(['nullable', 'boolean']),
            'model_capabilities.max_context_length' => $chatOnly(['nullable', 'integer', 'min:1', 'max:2000000']),
            'context_length' => $chatOnly(['nullable', 'integer', 'min:1', 'max:200000']),
            'temperature' => $chatOnly(['nullable', 'numeric', 'min:0', 'max:1']),
            'system_prompt_id' => $chatOnly(['nullable', 'integer', 'exists:ai_system_prompts,id']),
            'custom_system_prompt' => $chatOnly(['nullable', 'string']),
            'supports_tools' => $chatOnly(['boolean']),
            'allowed_tools' => $chatOnly(['nullable', 'array']),
            'allowed_tools.*' => $chatOnly(['string', 'max:255']),
            'web_tool_policy' => $chatOnly(['nullable', 'json', $webToolPolicyRule]),
            'supports_json_mode' => $chatOnly(['boolean']),
            'enable_thinking' => $chatOnly(['nullable', 'boolean']),
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function roleDependentRules(): array
    {
        return [
            'max_tokens' => ['required_if:role,chat', 'nullable', 'integer', 'min:1', 'max:200000'],
            'default_voice' => ['prohibited_unless:role,audio', 'nullable', 'string', 'max:100'],
            'feature_defaults' => ['nullable', 'array'],
            'feature_defaults.*' => ['string', Rule::in(array_keys(self::FEATURE_ROLES))],
        ];
    }

    /**
     * Rejects a provider that cannot serve the submitted role, naming both.
     */
    protected function providerServesRoleRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $provider = is_string($value) ? AiProvider::tryFrom($value) : null;
            $role = $this->submittedRole();

            if ($provider !== null && $role !== null && ! $provider->supportsRole($role)) {
                $fail("The {$provider->value} provider cannot serve the {$role->value} role.");
            }
        };
    }

    /**
     * A feature default must belong to the system's own role: a voice system
     * cannot hold a chat feature, and a chat system cannot hold a voice one.
     */
    protected function validateFeatureDefaultsMatchRole(Validator $validator): void
    {
        $role = $this->submittedRole();

        if ($role === null) {
            return;
        }

        foreach ((array) $this->input('feature_defaults', []) as $index => $feature) {
            $required = self::FEATURE_ROLES[$feature] ?? null;

            if ($required !== null && $required !== $role) {
                $validator->errors()->add(
                    "feature_defaults.{$index}",
                    "Only a {$required->value} system can be the {$feature} default.",
                );
            }
        }
    }

    protected function submittedRole(): ?AiSystemRole
    {
        $role = $this->input('role');

        return is_string($role) ? AiSystemRole::tryFrom($role) : null;
    }
}
