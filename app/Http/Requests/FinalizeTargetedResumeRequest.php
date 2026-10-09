<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinalizeTargetedResumeRequest extends FormRequest
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
            'tailored_content' => ['required', 'string'],
            'fit_score' => ['nullable', 'integer', 'min:1', 'max:100'],
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
            'tailored_content.required' => 'There is no tailored resume content to save.',
            'fit_score.integer' => 'The fit score must be a whole number from 1 to 100.',
            'fit_score.min' => 'The fit score must be a whole number from 1 to 100.',
            'fit_score.max' => 'The fit score must be a whole number from 1 to 100.',
        ];
    }
}
