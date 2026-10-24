<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Jvjvjv\CodeTalker\Services\Management\AiMcpServerManager;

/**
 * No slug rule: the slug is immutable, and the manager discards a submitted one.
 */
class UpdateAiMcpServerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return AiMcpServerManager::updateRules($this->all());
    }
}
