<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParseJobUrlRequest extends FormRequest
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
            'url' => ['required', 'url', 'max:2048'],
            'ai_system_id' => ['required', 'integer', 'exists:ai_systems,id'],
        ];
    }
}
