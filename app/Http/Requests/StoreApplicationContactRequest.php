<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesApplicationContact;
use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationContactRequest extends FormRequest
{
    use ValidatesApplicationContact;

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
        return $this->contactRules();
    }
}
