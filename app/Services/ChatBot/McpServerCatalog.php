<?php

namespace App\Services\ChatBot;

use Jvjvjv\CodeTalker\Models\AiMcpServerTool;
use Jvjvjv\CodeTalker\Services\Management\AiMcpServerManager;

/**
 * Admin-facing shapes of the remote MCP server catalog, built on the
 * manager's `list()` — which never includes a server's `auth`, so nothing
 * returned here can carry a credential.
 */
class McpServerCatalog
{
    public function __construct(private AiMcpServerManager $servers)
    {
    }

    /**
     * Every server with its sync health, catalog, and tool count.
     *
     * @return array<int, array<string, mixed>>
     */
    public function servers(): array
    {
        return array_map(
            static fn (array $server): array => [...$server, 'tool_count' => count($server['tools'])],
            $this->servers->list(),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function server(int $id): ?array
    {
        return collect($this->servers())->firstWhere('id', $id);
    }

    /**
     * Tag each tool from the tool-listing endpoint with where it comes from,
     * without changing which tools are listed.
     *
     * A name is remote when it is the exposed name of a synced tool on a
     * server that has not been deleted. A local tool that shadows a remote
     * name of the same spelling (which the package logs as a warning) would
     * be labelled remote here — exposed names are `{slug}__{tool}` and no
     * local tool uses that shape, so this is accepted.
     *
     * @param  array<int, array{name: string, description: string}>  $tools
     * @return array<int, array{name: string, description: string, source: string, server_slug: ?string}>
     */
    public function annotateSources(array $tools): array
    {
        $serverSlugByExposedName = AiMcpServerTool::query()
            ->whereIn('exposed_name', array_column($tools, 'name'))
            ->whereHas('server')
            ->with('server:id,slug')
            ->get()
            ->mapWithKeys(fn (AiMcpServerTool $tool): array => [$tool->exposed_name => $tool->server->slug]);

        return array_map(
            static fn (array $tool): array => [
                ...$tool,
                'source' => $serverSlugByExposedName->has($tool['name']) ? 'remote' : 'internal',
                'server_slug' => $serverSlugByExposedName->get($tool['name']),
            ],
            $tools,
        );
    }

    /**
     * The per-server catalog the AI system tool picker groups remote tools by.
     *
     * @return array<int, array{id: int, slug: string, name: string, enabled: bool, tools: array<int, array{exposed_name: string, description: ?string, available: bool, unavailable_reason: ?string}>}>
     */
    public function forToolPicker(): array
    {
        return array_map(
            static fn (array $server): array => [
                'id' => $server['id'],
                'slug' => $server['slug'],
                'name' => $server['name'],
                'enabled' => (bool) $server['enabled'],
                'tools' => array_map(
                    static fn (array $tool): array => [
                        'exposed_name' => $tool['exposed_name'],
                        'description' => $tool['description'],
                        'available' => (bool) $tool['available'],
                        'unavailable_reason' => $tool['unavailable_reason'],
                    ],
                    $server['tools'],
                ),
            ],
            $this->servers->list(),
        );
    }
}
