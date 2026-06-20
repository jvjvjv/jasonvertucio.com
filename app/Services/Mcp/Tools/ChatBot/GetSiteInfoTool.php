<?php

namespace App\Services\Mcp\Tools\ChatBot;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get-site-info')]
#[Description('Load site configuration — projects, skills overview, social links, and interests.')]
class GetSiteInfoTool extends Tool
{
    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $configPath = resource_path('config/config.json');

        if (!File::exists($configPath)) {
            return Response::error('Site config not found');
        }

        $config = json_decode(File::get($configPath), true);

        if (!\is_array($config)) {
            return Response::error('Could not parse site config');
        }

        // Return only the parts useful for a chatbot context — skip nav links and internal config
        return Response::structured(array_filter([
            'html_title' => $config['html_title'] ?? null,
            'projects' => $config['projects'] ?? null,
            'skills' => $config['skills'] ?? null,
            'social' => $config['social'] ?? null,
            'interests' => $config['interests'] ?? null,
        ], static fn (mixed $v): bool => $v !== null));
    }
}
