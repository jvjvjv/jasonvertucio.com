<?php

namespace App\Services\Mcp\Tools\ChatBot;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
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
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $payload = $this->siteInfoPayload();

        return $payload === null
            ? Response::error('Site config not found or unreadable')
            : Response::structured($payload);
    }

    /**
     * Build the public site profile, or null when the config cannot be read.
     *
     * Separated from {@see handle()} so the public MCP endpoint can cache the
     * array. See {@see \App\Mcp\Tools\PublicSiteInfoTool}.
     *
     * @return array<string, mixed>|null
     */
    protected function siteInfoPayload(): ?array
    {
        $configPath = resource_path('config/config.json');

        if (! File::exists($configPath)) {
            return null;
        }

        $config = json_decode(File::get($configPath), true);

        if (! \is_array($config)) {
            return null;
        }

        // Only content already rendered on the public homepage. `links` is
        // navigation — it includes /admin, /canvas, /profile and /logout — and
        // is deliberately absent.
        //
        // This list previously also asked for `skills` and `social`, neither of
        // which is a key in config.json, so array_filter silently dropped them.
        // They are gone rather than wired up: resume skills already reach
        // callers through get-resume-data, and adding a `social` key to the
        // config for some unrelated reason should not start publishing it here.
        return array_filter([
            'html_title' => $config['html_title'] ?? null,
            'projects' => $config['projects'] ?? null,
            'interests' => $config['interests'] ?? null,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
