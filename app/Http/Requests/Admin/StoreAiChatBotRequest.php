<?php

namespace App\Http\Requests\Admin;

use App\Models\AiChatBot;
use BSPDX\Keystone\Models\KeystonePermission;
use Illuminate\Foundation\Http\FormRequest;
use Jvjvjv\CodeTalker\Services\Management\AiPersonaManager;

class StoreAiChatBotRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        $allowedValues = array_merge(
            [AiChatBot::PERMISSION_AUTHENTICATED],
            KeystonePermission::pluck('name')->all(),
        );

        return array_merge(AiPersonaManager::createRules($this->all()), [
            'required_permission' => ['nullable', 'string', 'in:'.implode(',', $allowedValues)],
        ]);
    }
}
