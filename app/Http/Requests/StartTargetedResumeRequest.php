<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartTargetedResumeRequest extends FormRequest
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
            'ai_system_id'   => ['required', 'integer', 'exists:ai_systems,id'],
            'job_title'       => ['nullable', 'string', 'max:255'],
            'job_description' => ['required', 'string'],
        ];
    }
}
