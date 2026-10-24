<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAiMcpServerRequest;
use App\Http\Requests\Admin\UpdateAiMcpServerRequest;
use App\Services\ChatBot\McpServerCatalog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Jvjvjv\CodeTalker\Models\AiMcpServer;
use Jvjvjv\CodeTalker\Services\Management\AiMcpServerManager;

/**
 * Admin screens for external MCP servers, built on the package's
 * `AiMcpServerManager` (code-talker 0.16.0 ships the service layer only).
 *
 * Saving a server syncs its tool catalog. A failed sync never fails the save —
 * the manager records it on the server and reports it, and it is surfaced here
 * as an error flash on an otherwise successful redirect.
 */
class AiMcpServerController extends Controller
{
    public function __construct(
        private AiMcpServerManager $servers,
        private McpServerCatalog $catalog,
    ) {
    }

    public function index(): InertiaResponse
    {
        return Inertia::render('ai/mcp-servers/Index', [
            'servers' => $this->catalog->servers(),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('ai/mcp-servers/Create', [
            'authTypes' => AiMcpServerManager::AUTH_TYPES,
        ]);
    }

    public function store(StoreAiMcpServerRequest $request): RedirectResponse
    {
        ['server' => $server, 'sync' => $sync] = $this->servers->create($request->validated());

        return $this->redirectWithSyncReport($server, $sync, 'created');
    }

    public function edit(AiMcpServer $aiMcpServer): InertiaResponse
    {
        return Inertia::render('ai/mcp-servers/Edit', [
            'server' => $this->catalog->server($aiMcpServer->id),
            'authTypes' => AiMcpServerManager::AUTH_TYPES,
        ]);
    }

    /**
     * An `auth` key absent from the request keeps the stored credentials — the
     * edit form only sends one when the administrator changed it.
     */
    public function update(UpdateAiMcpServerRequest $request, AiMcpServer $aiMcpServer): RedirectResponse
    {
        ['server' => $server, 'sync' => $sync] = $this->servers->update($aiMcpServer, $request->validated());

        return $this->redirectWithSyncReport($server, $sync, 'updated');
    }

    public function sync(AiMcpServer $aiMcpServer): RedirectResponse
    {
        return $this->redirectWithSyncReport($aiMcpServer, $this->servers->sync($aiMcpServer), 'synced');
    }

    public function destroy(AiMcpServer $aiMcpServer): RedirectResponse
    {
        $name = $aiMcpServer->name;
        $systemCount = $this->servers->delete($aiMcpServer);

        $message = $systemCount > 0
            ? "MCP server \"{$name}\" deleted. {$systemCount} AI system(s) had granted one of its tools."
            : "MCP server \"{$name}\" deleted successfully.";

        return redirect()->route('admin.ai.mcp-servers.index')->with('success', $message);
    }

    /**
     * @param  array{server: string, stored: int, unrepresentable: int, error: ?string}  $sync
     */
    private function redirectWithSyncReport(AiMcpServer $server, array $sync, string $action): RedirectResponse
    {
        $redirect = redirect()->route('admin.ai.mcp-servers.edit', $server);

        if ($sync['error'] !== null) {
            return $redirect->with('error', "MCP server \"{$server->name}\" {$action}, but its tool sync failed: {$sync['error']}");
        }

        return $redirect->with(
            'success',
            "MCP server \"{$server->name}\" {$action}. Synced {$sync['stored']} tool(s), {$sync['unrepresentable']} unavailable.",
        );
    }
}
