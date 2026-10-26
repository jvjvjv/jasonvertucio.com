<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchSessionSystemRequest extends FormRequest
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
            'ai_system_id' => ['required', 'integer', 'exists:ai_systems,id'],
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
            'ai_system_id.required' => 'Choose an AI system to switch to.',
            'ai_system_id.integer' => 'Choose an AI system to switch to.',
            'ai_system_id.exists' => 'That AI system no longer exists.',
        ];
    }
}
