<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiChatBotRequest extends FormRequest
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
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $botId = $this->route('aiChatBot')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('ai_chat_bots', 'slug')->ignore($botId)],
            'description' => ['nullable', 'string'],
            'ai_system_id' => ['required', 'integer', 'exists:ai_systems,id'],
            'prompt_template' => ['required', 'string'],
            'allowed_roles' => ['nullable', 'array'],
            'allowed_roles.*' => ['string', 'max:255'],
            'is_active' => ['boolean'],
            'is_public' => ['boolean'],
            'require_visitor_identity' => ['boolean'],
        ];
    }
}
