## Why

code-talker 0.16.0 lets an `AiSystem` use tools hosted on external MCP servers. Each server's tools are exposed as `{slug}__{tool}` and granted through `AiSystem::allowed_tools`, just like the package's own tools. 0.16.1 is a docs-only follow-up. The package ships this as a service layer (`AiMcpServerManager`) and ships no screens, so the site currently has no way to register a server, see whether it synced, or grant its tools. Its two migrations also haven't been run locally yet.

## What Changes

- Bump `jvjvjv/code-talker` to 0.16.1 (the existing `^0` constraint already allows it). Run the pending `ai_mcp_servers` / `ai_mcp_server_tools` migrations on both the app database and the test database.
- Add an **MCP Servers** admin section at `/admin/ai/mcp-servers`, gated on `manage-ai-tools` like the rest of `/admin/ai`:
  - List every server with its status (enabled, last synced, last sync error) and its tool count.
  - Create a server: name, slug, URL, timeout, enabled, and auth. Auth is one of none, bearer token, custom headers, or OAuth client credentials.
  - Edit a server. The slug is read-only after creation. Stored credentials are never sent back to the browser, and leaving auth untouched keeps them.
  - Sync a server on demand and report the result (tools stored, tools unavailable, or the error).
  - Show a server's synced tool catalog, marking tools that can't be offered and why.
  - Delete a server after confirmation, reporting how many AI systems had granted one of its tools.
- Extend the AI system's tool picker, on both the create and edit pages, so remote MCP tools can be allowed or disallowed with the same checkboxes as internal tools, grouped by their server. Tools that can't be offered, and grants that no longer match any tool, are shown so an admin can see and remove them.
- Add an "MCP Servers" entry to the admin navigation.

## Capabilities

### New Capabilities

- `mcp-server-admin`: managing external MCP server records from the admin area — listing, creating, editing, syncing, catalog display, credential handling, and deletion.
- `ai-system-tool-grants`: how an AI system's allowed tools are chosen in the admin area, covering both internal and remote MCP tools, including unavailable tools and stale grants.

### Modified Capabilities

_None._ No existing spec covers the AI system admin screens or tool granting.

## Impact

- **Dependencies**: `composer.lock` (code-talker 0.16.0 → 0.16.1). This needs approval under project rules; the request names 0.16.1 explicitly. The `jv-app` container has its own `vendor/` volume, so it needs the same update.
- **Database**: two package-provided migrations, run on both `jasonvertucio` and `wink`. There's no host migration.
- **Backend**: a new `Admin\AiMcpServerController` plus form requests built on `AiMcpServerManager`'s `createRules()`/`updateRules()`, routes in `routes/codetalker-admin.php`, and `AiChatBotController::mcpTools()` enriched with each tool's source and availability. The last is an additive JSON change, so the persona pages are unaffected.
- **Frontend**: new `resources/js/admin/pages/ai/mcp-servers/{Index,Create,Edit,Form}.tsx`, an auth editor component, `AvailableMcpTools.tsx` grouping, and `navigation.json`.
- **Scheduling**: the package already schedules `ai:sync-mcp-servers` daily at 03:30. Nothing to add; production must be running the scheduler.
- **Production deploy** (developer-owned): `composer install`, `php artisan migrate`, and restart the queue worker.
