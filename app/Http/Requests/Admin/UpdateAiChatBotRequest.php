<?php

namespace App\Http\Requests\Admin;

use App\Models\AiChatBot;
use Illuminate\Foundation\Http\FormRequest;
use Jvjvjv\CodeTalker\Services\Management\AiChatBotManager;

class UpdateAiChatBotRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        /** @var AiChatBot|null $bot */
        $bot = $this->route('aiChatBot');

        return array_merge(AiChatBotManager::updateRules($this->all(), $bot), [
            'allowed_roles' => ['nullable', 'array'],
            'allowed_roles.*' => ['string'],
        ]);
    }
}
