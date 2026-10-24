<?php

namespace App\Http\Requests;

use App\Enums\IntelStartMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartCompanyIntelRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ai_system_id' => ['nullable', 'integer', 'exists:ai_systems,id'],
            'mode' => ['required', Rule::enum(IntelStartMode::class)],
        ];
    }

    /**
     * Get the custom error messages for the defined rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ai_system_id.exists' => 'That AI system no longer exists.',
            'mode.required' => 'Choose whether the research runs in the background or here.',
            'mode.enum' => 'Choose whether the research runs in the background or here.',
        ];
    }

    public function mode(): IntelStartMode
    {
        return IntelStartMode::from($this->validated('mode'));
    }
}
