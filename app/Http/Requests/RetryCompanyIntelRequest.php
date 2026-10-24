<?php

namespace App\Http\Requests;

use App\Enums\IntelStartMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RetryCompanyIntelRequest extends FormRequest
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
            'mode' => ['required', Rule::enum(IntelStartMode::class)],
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
            'mode.required' => 'Choose whether the research runs in the background or here.',
            'mode.enum' => 'Choose whether the research runs in the background or here.',
        ];
    }

    public function mode(): IntelStartMode
    {
        return IntelStartMode::from($this->validated('mode'));
    }
}
