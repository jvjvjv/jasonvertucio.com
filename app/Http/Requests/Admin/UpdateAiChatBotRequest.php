<?php

namespace App\Http\Requests\Admin;

use Jvjvjv\CodeTalker\Http\Requests\Admin\UpdateAiChatBotRequest as BaseRequest;

class UpdateAiChatBotRequest extends BaseRequest
{
    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'allowed_roles' => ['nullable', 'array'],
            'allowed_roles.*' => ['string'],
        ]);
    }
}
