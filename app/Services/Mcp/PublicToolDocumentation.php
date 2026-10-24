<?php

namespace App\Services\Mcp;

use App\Mcp\Servers\PublicServer;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Transport\FakeTransporter;

/**
 * Reads the public MCP server's own tool roster for display.
 *
 * The documentation is generated rather than written so it cannot drift: add a
 * tool to {@see PublicServer::$tools} and the page documents it on deploy;
 * remove one and it disappears. A hand-maintained list would go stale the first
 * time the roster changed, and stale documentation is worse than none — a
 * caller would build against a tool that no longer exists.
 *
 * `createContext()` is public API and never touches the transport, so the
 * package's own `FakeTransporter` is enough to read the roster outside a
 * request. Tools are asked only for metadata; no handler runs, so nothing is
 * authorized, cached or logged by rendering the page.
 */
class PublicToolDocumentation
{
    /**
     * @param  class-string<Server>  $serverClass
     * @return array<int, array{name: string, description: string|null, arguments: array<int, array{name: string, description: string|null, required: bool}>}>
     */
    public function forServer(string $serverClass = PublicServer::class): array
    {
        $server = new $serverClass(new FakeTransporter);

        return collect($server->createContext()->tools())
            ->map(fn ($tool): array => $this->describe($tool->toArray()))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $tool
     * @return array{name: string, description: string|null, arguments: array<int, array{name: string, description: string|null, required: bool}>}
     */
    private function describe(array $tool): array
    {
        $schema = (array) ($tool['inputSchema'] ?? []);

        // A tool with no arguments serializes `properties` as an empty JSON
        // object, which arrives here as stdClass rather than an array.
        $properties = (array) ($schema['properties'] ?? []);
        $required = (array) ($schema['required'] ?? []);

        $arguments = [];

        foreach ($properties as $name => $definition) {
            $definition = (array) $definition;

            $arguments[] = [
                'name' => (string) $name,
                'description' => isset($definition['description']) ? (string) $definition['description'] : null,
                'required' => in_array($name, $required, true),
            ];
        }

        return [
            'name' => (string) ($tool['name'] ?? ''),
            'description' => isset($tool['description']) ? (string) $tool['description'] : null,
            'arguments' => $arguments,
        ];
    }
}
