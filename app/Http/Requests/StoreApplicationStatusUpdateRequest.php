<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationStatusUpdateRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::in(array_column(ApplicationStatus::pipeline(), 'value'))],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'status.required' => 'Choose a status for this entry.',
            'status.in' => 'Status history entries must use a pipeline status.',
            'notes.max' => 'Notes are limited to 1,000 characters.',
            'occurred_at.date' => 'The date is not a valid date.',
        ];
    }

    public function status(): ApplicationStatus
    {
        return ApplicationStatus::from($this->validated('status'));
    }
}
