<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
{
    /**
     * Begin an AI analysis of the job.
     */
    public const string INTENT_ANALYZE = 'analyze';

    /**
     * Record a job that was already applied to with a main resume.
     */
    public const string INTENT_APPLIED = 'applied';

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
            'intent' => ['required', 'string', Rule::in([self::INTENT_ANALYZE, self::INTENT_APPLIED])],
            'job_description' => ['required', 'string'],
            'job_title' => ['nullable', 'string', 'max:191'],
            'company_name' => ['nullable', 'string', 'max:191'],
            'job_location' => ['nullable', 'string', 'max:191'],
            'job_url_id' => ['nullable', 'string', 'exists:job_urls,id'],
            'ai_system_id' => ['required_if:intent,'.self::INTENT_ANALYZE, 'nullable', 'integer', 'exists:ai_systems,id'],
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
            'intent.required' => 'Choose whether to begin an analysis or record an application.',
            'intent.in' => 'Choose whether to begin an analysis or record an application.',
            'job_description.required' => 'A job description is required.',
            'ai_system_id.required_if' => 'Choose an AI system to run the analysis.',
            'ai_system_id.exists' => 'That AI system no longer exists.',
            'resume_version_id.exists' => 'That resume version no longer exists.',
            'occurred_at.date' => 'The applied date is not a valid date.',
        ];
    }

    public function wantsAnalysis(): bool
    {
        return $this->validated('intent') === self::INTENT_ANALYZE;
    }
}
