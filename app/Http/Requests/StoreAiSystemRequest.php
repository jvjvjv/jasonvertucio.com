<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Jvjvjv\CodeTalker\Enums\AiProvider;

class StoreAiSystemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', Rule::in(AiProvider::values())],
            'api_key' => [
                Rule::requiredIf(fn (): bool => ! in_array($this->input('provider'), [AiProvider::OpenAICompatible->value, AiProvider::LmStudio->value], true)),
                'nullable',
                'string',
            ],
            'model' => ['required', 'string', 'max:255'],
            'model_capabilities' => ['nullable', 'array'],
            'model_capabilities.reasoning' => ['nullable', 'boolean'],
            'model_capabilities.vision' => ['nullable', 'boolean'],
            'model_capabilities.tools' => ['nullable', 'boolean'],
            'model_capabilities.max_context_length' => ['nullable', 'integer', 'min:1', 'max:2000000'],
            'base_url' => ['nullable', 'string', 'url', 'max:255'],
            'api_version' => ['nullable', 'string', 'max:50'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:200000'],
            'context_length' => ['nullable', 'integer', 'min:1', 'max:200000'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'is_active' => ['boolean'],
            'system_prompt_id' => ['nullable', 'integer', 'exists:ai_system_prompts,id'],
            'custom_system_prompt' => ['nullable', 'string'],
            'config' => ['nullable', 'json'],
            'credentials' => ['nullable', 'json'],
            'auth_type' => ['nullable', 'string', 'max:50'],
            'endpoint_type' => ['nullable', 'string', 'max:50'],
            'stream_protocol' => ['nullable', 'string', 'max:50'],
            'system_prompt_mode' => ['nullable', 'string', 'max:50'],
            'supports_tools' => ['boolean'],
            'allowed_tools' => ['nullable', 'array'],
            'allowed_tools.*' => ['string', 'max:255'],
            'web_tool_policy' => ['nullable', 'json', $this->webToolPolicyRule()],
            'supports_json_mode' => ['boolean'],
            'enable_thinking' => ['nullable', 'boolean'],
            'is_local_endpoint' => ['boolean'],
            // @deprecated Removed from the admin UI; kept validated only so
            // an existing value round-trips untouched, not for new writes.
            // Slated for removal from the app entirely.
            'pricing_profile' => ['nullable', 'json'],
            'feature_defaults' => ['nullable', 'array'],
            'feature_defaults.*' => ['string', 'in:targeted-resume,cover-letter'],
        ];
    }

    /**
     * Validate the decoded shape of `web_tool_policy`: an `allowed_domains`
     * list of strings and/or a `credentials` map of host => header map.
     * Mirrors `Jvjvjv\CodeTalker\Services\Management\AiSystemManager`'s own
     * (private) rule, since this app validates AiSystem writes independently
     * rather than through that manager.
     */
    private function webToolPolicyRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            $decoded = is_string($value) ? json_decode($value, true) : $value;

            if ($decoded === null) {
                return;
            }

            if (! is_array($decoded)) {
                $fail('The :attribute must decode to an object.');

                return;
            }

            if (array_key_exists('allowed_domains', $decoded)) {
                $domains = $decoded['allowed_domains'];

                if (! is_array($domains) || array_filter($domains, static fn (mixed $d): bool => ! is_string($d)) !== []) {
                    $fail('The :attribute allowed_domains must be an array of strings.');

                    return;
                }
            }

            if (array_key_exists('credentials', $decoded)) {
                $credentials = $decoded['credentials'];

                if (! is_array($credentials)) {
                    $fail('The :attribute credentials must be an object keyed by host.');

                    return;
                }

                foreach ($credentials as $headers) {
                    if (! is_array($headers) || array_filter($headers, static fn (mixed $h): bool => ! is_string($h) && ! is_numeric($h)) !== []) {
                        $fail('The :attribute credentials must map each host to a header map of strings.');

                        return;
                    }
                }
            }
        };
    }
}
