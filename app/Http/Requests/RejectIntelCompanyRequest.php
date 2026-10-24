<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectIntelCompanyRequest extends FormRequest
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
            'company_id' => ['nullable', 'integer', 'exists:companies,id', 'required_without:hint', 'prohibits:hint'],
            'hint' => ['nullable', 'string', 'max:500', 'required_without:company_id'],
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
            'company_id.exists' => 'That company no longer exists.',
            'company_id.required_without' => 'Give the correct company, or choose an existing one.',
            'company_id.prohibits' => 'Give a hint or choose a company, not both.',
            'hint.required_without' => 'Give the correct company, or choose an existing one.',
            'hint.max' => 'Keep the hint under 500 characters.',
        ];
    }
}
