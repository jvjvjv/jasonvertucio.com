<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobUrlParserRequest extends FormRequest
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
            'domain' => ['required', 'string', 'max:255'],
            'company_name_selector' => ['nullable', 'string', 'max:255'],
            'job_title_selector' => ['nullable', 'string', 'max:255'],
            'job_location_selector' => ['nullable', 'string', 'max:255'],
            'job_description_selector' => ['nullable', 'string', 'max:255'],
            'ai_reasoning' => ['nullable', 'string'],
            'html' => ['nullable', 'string'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
