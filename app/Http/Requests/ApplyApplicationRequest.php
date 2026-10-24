<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyApplicationRequest extends FormRequest
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
            'resume_version_id' => ['nullable', 'integer', 'exists:resume_versions,id'],
            'occurred_at' => ['nullable', 'date'],
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
            'resume_version_id.exists' => 'That resume version no longer exists.',
            'occurred_at.date' => 'The applied date is not a valid date.',
        ];
    }
}
