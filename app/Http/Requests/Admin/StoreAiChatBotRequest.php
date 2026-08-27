<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Jvjvjv\CodeTalker\Services\Management\AiPersonaManager;

class StoreAiChatBotRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(AiPersonaManager::createRules($this->all()), [
            'allowed_roles' => ['nullable', 'array'],
            'allowed_roles.*' => ['string'],
        ]);
    }
}
