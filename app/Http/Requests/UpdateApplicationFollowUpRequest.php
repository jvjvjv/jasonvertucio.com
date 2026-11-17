<?php

namespace App\Http\Requests;

use App\Services\ApplicationFollowUpService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationFollowUpRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:'.ApplicationFollowUpService::SUBJECT_LIMIT],
            'body' => ['required', 'string', 'max:'.ApplicationFollowUpService::BODY_LIMIT],
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
            'subject.required' => 'A follow-up needs a subject.',
            'subject.max' => 'Subjects are limited to '.ApplicationFollowUpService::SUBJECT_LIMIT.' characters.',
            'body.required' => 'A follow-up needs a body.',
            'body.max' => 'The body is limited to '.number_format(ApplicationFollowUpService::BODY_LIMIT).' characters.',
        ];
    }
}
