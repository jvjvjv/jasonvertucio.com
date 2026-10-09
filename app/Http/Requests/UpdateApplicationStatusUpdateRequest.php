<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationStatusUpdateRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:1000'],
            'occurred_at' => ['required', 'date'],
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
            'notes.max' => 'Notes are limited to 1,000 characters.',
            'occurred_at.required' => 'A status entry needs a date.',
            'occurred_at.date' => 'The date is not a valid date.',
        ];
    }
}
