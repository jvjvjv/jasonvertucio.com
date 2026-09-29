<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Jvjvjv\CodeTalker\Services\Management\AiMcpServerManager;

class StoreAiMcpServerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return AiMcpServerManager::createRules($this->all());
    }
}
