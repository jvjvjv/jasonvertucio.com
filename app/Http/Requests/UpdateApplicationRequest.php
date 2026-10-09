<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:191'],
            'company_name' => ['nullable', 'string', 'max:191'],
            'job_title' => ['nullable', 'string', 'max:191'],
            'location' => ['nullable', 'string', 'max:191'],
            'job_description' => ['sometimes', 'string'],
            'fit_score' => ['nullable', 'integer', 'min:1', 'max:100'],
            'fit_summary' => ['nullable', 'string'],
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
            'job_description.string' => 'A job description cannot be blank.',
            'fit_score.integer' => 'The fit score must be a whole number from 1 to 100.',
            'fit_score.min' => 'The fit score must be a whole number from 1 to 100.',
            'fit_score.max' => 'The fit score must be a whole number from 1 to 100.',
        ];
    }

    /**
     * The validated input keyed the way the application stores it: the
     * form's `job_title` is the application's `position`. Only the keys
     * that were sent are returned.
     *
     * @return array{
     *     title?: ?string,
     *     company_name?: ?string,
     *     position?: ?string,
     *     location?: ?string,
     *     job_description?: string,
     *     fit_score?: ?int,
     *     fit_summary?: ?string
     * }
     */
    public function details(): array
    {
        $details = $this->validated();

        if (array_key_exists('job_title', $details)) {
            $details['position'] = $details['job_title'];
            unset($details['job_title']);
        }

        return $details;
    }
}
