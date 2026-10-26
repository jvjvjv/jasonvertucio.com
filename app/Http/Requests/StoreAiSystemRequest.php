<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesAiSystemRole;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Jvjvjv\CodeTalker\Enums\AiProvider;
use Jvjvjv\CodeTalker\Enums\AiSystemRole;

class StoreAiSystemRequest extends FormRequest
{
    use ValidatesAiSystemRole;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A system created without a role is a chat system, as every system was
     * before roles existed.
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('role'))) {
            $this->merge(['role' => AiSystemRole::Chat->value]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::enum(AiSystemRole::class)],
            'provider' => ['required', 'string', Rule::in(AiProvider::values()), $this->providerServesRoleRule()],
            'api_key' => [
                Rule::requiredIf(fn (): bool => ! in_array($this->input('provider'), [AiProvider::OpenAICompatible->value, AiProvider::LmStudio->value], true)),
                'nullable',
                'string',
            ],
            // A voice system may leave the model blank to use the provider's
            // default; a local server needs it set, which the form says.
            'model' => ['required_if:role,chat', 'nullable', 'string', 'max:255'],
            'base_url' => ['nullable', 'string', 'url', 'max:255'],
            'api_version' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            ...$this->chatOnlyRules($this->webToolPolicyRule()),
            ...$this->roleDependentRules(),
            'config' => ['nullable', 'json'],
            'credentials' => ['nullable', 'json'],
            'auth_type' => ['nullable', 'string', 'max:50'],
            'endpoint_type' => ['nullable', 'string', 'max:50'],
            'stream_protocol' => ['nullable', 'string', 'max:50'],
            'system_prompt_mode' => ['nullable', 'string', 'max:50'],
            'is_local_endpoint' => ['boolean'],
            // @deprecated Removed from the admin UI; kept validated only so
            // an existing value round-trips untouched, not for new writes.
            // Slated for removal from the app entirely.
            'pricing_profile' => ['nullable', 'json'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateFeatureDefaultsMatchRole($validator);
        });
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
