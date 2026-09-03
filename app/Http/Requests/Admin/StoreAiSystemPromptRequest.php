<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Jvjvjv\CodeTalker\Services\Management\AiSystemPromptManager;

class StoreAiSystemPromptRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return AiSystemPromptManager::rules();
    }
}
