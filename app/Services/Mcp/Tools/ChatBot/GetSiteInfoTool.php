<?php

namespace App\Services\Mcp\Tools\ChatBot;

use Jvjvjv\CodeTalker\Contracts\Mcp\AiToolHandlerContract;
use Illuminate\Support\Facades\File;

class GetSiteInfoTool implements AiToolHandlerContract
{
    public function name(): string
    {
        return 'get_site_info';
    }

    public function description(): string
    {
        return 'Load site configuration — projects, skills overview, social links, and interests.';
    }

    public function schema(): array
    {
        return ['type' => 'object', 'properties' => (object) [], 'required' => []];
    }

    public function handle(array $input): array
    {
        $configPath = resource_path('config/config.json');

        if (!File::exists($configPath)) {
            return ['error' => 'Site config not found'];
        }

        $config = json_decode(File::get($configPath), true);

        if (!\is_array($config)) {
            return ['error' => 'Could not parse site config'];
        }

        // Return only the parts useful for a chatbot context — skip nav links and internal config
        return array_filter([
            'html_title' => $config['html_title'] ?? null,
            'projects' => $config['projects'] ?? null,
            'skills' => $config['skills'] ?? null,
            'social' => $config['social'] ?? null,
            'interests' => $config['interests'] ?? null,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
