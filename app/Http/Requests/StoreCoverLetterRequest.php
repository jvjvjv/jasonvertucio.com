<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCoverLetterRequest extends FormRequest
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
            'resume_version_id' => ['required', 'integer', 'exists:resume_versions,id'],
            'company_name' => ['required', 'string', 'max:255'],
            'position' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'company_address' => ['nullable', 'string'],
            'greeting' => ['required', 'string', 'max:255'],
            'message_body' => ['required', 'string'],
            'closing' => ['nullable', 'string', 'max:255'],
            'signature' => ['nullable', 'string', 'max:255'],
        ];
    }
}
