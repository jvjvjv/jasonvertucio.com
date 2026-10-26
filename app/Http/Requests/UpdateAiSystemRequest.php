<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesAiSystemRole;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Jvjvjv\CodeTalker\Enums\AiProvider;
use Jvjvjv\CodeTalker\Enums\AiSystemRole;
use Jvjvjv\CodeTalker\Models\AiSystem;

class UpdateAiSystemRequest extends FormRequest
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
     * The role locks with the provider. An established system's stored role
     * replaces whatever was submitted, so the role-dependent rules read the
     * role the system will keep; a pending duplicate keeps its own when none
     * was submitted.
     */
    protected function prepareForValidation(): void
    {
        /** @var AiSystem $aiSystem */
        $aiSystem = $this->route('aiSystem');
        $storedRole = ($aiSystem->role ?? AiSystemRole::Chat)->value;

        if ($aiSystem->duplicated_at === null || blank($this->input('role'))) {
            $this->merge(['role' => $storedRole]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * A freshly duplicated system (duplicated_at is set) is still on its
     * first edit, so provider/model/api_key are validated exactly like
     * StoreAiSystemRequest instead of being immutable. Once that first save
     * clears duplicated_at, provider and model are omitted again. The key is
     * always accepted: it is write-only, and replacing it must not require a
     * duplicate.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var AiSystem $aiSystem */
        $aiSystem = $this->route('aiSystem');

        $pendingFirstEditRules = $aiSystem?->duplicated_at !== null
            ? [
                'role' => ['required', Rule::enum(AiSystemRole::class)],
                'provider' => ['required', 'string', Rule::in(AiProvider::values()), $this->providerServesRoleRule()],
                'api_key' => [
                    Rule::requiredIf(fn (): bool => $this->requiresNewApiKey($aiSystem)),
                    'nullable',
                    'string',
                ],
                'model' => ['required_if:role,chat', 'nullable', 'string', 'max:255'],
            ]
            : ['api_key' => ['nullable', 'string']];

        return [
            'name' => ['required', 'string', 'max:255'],
            ...$pendingFirstEditRules,
            'base_url' => ['nullable', 'string', 'url', 'max:255'],
            'api_version' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            ...$this->chatOnlyRules($this->webToolPolicyRule()),
            ...$this->roleDependentRules(),
            'config' => ['nullable', 'json'],
            'credentials' => ['nullable', 'json'],
            'clear_api_key' => ['boolean'],
            'clear_credentials' => ['boolean'],
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

    /**
     * The key is write-only, so it must not be redirectable: pointing a system
     * at another base URL while keeping its stored key would deliver that key
     * to the new address the next time the system is used. Changing the base URL
     * therefore requires entering the key — and any stored credentials, which
     * travel to the same address — again, or removing them.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateFeatureDefaultsMatchRole($validator);
        });

        $validator->after(function (Validator $validator): void {
            /** @var AiSystem $aiSystem */
            $aiSystem = $this->route('aiSystem');
            $requested = trim((string) $this->input('base_url'));

            $normalize = static fn (?string $url): string => rtrim(strtolower(trim((string) $url)), '/');

            if ($requested === '' || $normalize($requested) === $normalize($aiSystem->base_url)) {
                return;
            }

            $keyWouldBeKept = filled($aiSystem->api_key)
                && blank($this->input('api_key'))
                && ! $this->boolean('clear_api_key');
            $credentialsWouldBeKept = filled($aiSystem->getRawOriginal('credentials'))
                && blank($this->input('credentials'))
                && ! $this->boolean('clear_credentials');

            if ($keyWouldBeKept) {
                $validator->errors()->add('api_key', 'Enter the API key again to change the base URL.');
            }

            if ($credentialsWouldBeKept) {
                $validator->errors()->add('credentials', 'Enter the credentials again to change the base URL.');
            }
        });
    }

    /**
     * A pending duplicate carries its original's key server-side, so a key is
     * demanded only when it has none or the provider it was issued for changed.
     */
    private function requiresNewApiKey(AiSystem $aiSystem): bool
    {
        $provider = $this->input('provider');

        if (in_array($provider, [AiProvider::OpenAICompatible->value, AiProvider::LmStudio->value], true)) {
            return false;
        }

        return $provider !== $aiSystem->provider || blank($aiSystem->api_key);
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
