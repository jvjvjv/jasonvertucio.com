## 1. Dependency and schema

- [x] 1.1 Run `composer update jvjvjv/code-talker` on the host and inside `jv-app` (separate `vendor/` volume). Verify: `composer show jvjvjv/code-talker` reports 0.16.1 in both places.
- [x] 1.2 Run the pending package migrations on the app DB (`php artisan migrate` in `jv-app`) and on the test DB (`DB_DATABASE=wink php artisan migrate`). Verify: `migrate:status` shows `create_ai_mcp_servers_table` and `create_ai_mcp_server_tools_table` as Ran for both databases.

## 2. MCP server admin backend

- [x] 2.1 Create `App\Http\Requests\Admin\StoreAiMcpServerRequest` and `UpdateAiMcpServerRequest`, returning `AiMcpServerManager::createRules($this->all())` / `updateRules($this->all())`, following `StoreAiSystemPromptRequest`. Verify: the form-request validation tests in 2.5 pass.
- [x] 2.2 Create `App\Http\Controllers\Admin\AiMcpServerController` over `AiMcpServerManager`, per design Decisions 1–3:
  - `index` renders `ai/mcp-servers/Index` with `servers` from `list()`, each with a `tool_count`.
  - `create` renders `ai/mcp-servers/Create`.
  - `store` calls `create()` and redirects to edit with a success or error flash from the sync report.
  - `edit` renders `ai/mcp-servers/Edit` with that server's `list()` entry.
  - `update` calls `update()` with `auth` omitted when absent from the request.
  - `sync` calls `sync()`.
  - `destroy` calls `delete()` and flashes the affected-system count.

  Verify: controller tests in 2.5 pass.
- [x] 2.3 Register the routes in `routes/codetalker-admin.php` inside the existing `admin.ai.` group: `mcp-servers.index|create|store|edit|update|sync|destroy`, with `/mcp-servers/new` before `/mcp-servers/{aiMcpServer}`. Verify: `php artisan route:list --path=admin/ai/mcp-servers` lists all seven with the `can:manage-ai-tools` middleware.
- [x] 2.4 Add the "MCP Servers" entry to `resources/js/admin/navigation.json` after "System Prompts". Use an icon present in `resources/js/admin/utils/iconRegistry.tsx`, adding `Hub` there if needed. Verify: the nav test in 2.5 passes.
- [x] 2.5 Add `tests/Feature/AiMcpServerAdminTest.php` (`DatabaseTransactions`). Bind a Mockery double of `Jvjvjv\CodeTalker\Services\Mcp\Remote\RemoteToolCatalogSync` returning a chosen report, since `FakeMcpServer` is package-test-only. Cover every scenario in `specs/mcp-server-admin/spec.md`:
  - guest → login; no permission → 403 on every route
  - the list shows sync health, and `auth` is absent from the props (assert the token string isn't in the response body)
  - a create syncs and flashes success; a failing sync still saves and flashes the error
  - validation errors for a bad slug, a duplicate slug, a non-HTTP URL, and bearer auth with no token
  - an update that omits `auth` keeps the decrypted token; a submitted slug is ignored; switching to `none` clears the token
  - manual sync flashes the counts
  - the edit page lists catalog tools, including an unavailable one with its reason (seed `AiMcpServerTool` rows)
  - delete soft-deletes and reports the count of systems granting its tools
  - the AI landing page's `navBlocks` include `/admin/ai/mcp-servers`

  Verify: `vendor/bin/phpunit tests/Feature/AiMcpServerAdminTest.php` passes.

## 3. MCP server admin frontend

- [x] 3.1 Add the `McpServer` / `McpServerTool` types to `resources/js/types/index.ts`, matching the `list()` shape. Verify: `npx tsc --noEmit` passes.
- [x] 3.2 Build `resources/js/admin/components/McpServerAuthEditor.tsx`. It has an auth type select (none, bearer, headers, client credentials) and per-type fields: token; header key/value rows; client ID, client secret, scope, token endpoint. It tracks a dirty flag so an unchanged auth is omitted from the submit, and it shows the "Stored — leave blank to keep" placeholder on edit (design Decision 3). Verify: `npx tsc --noEmit` and `npm run lint` pass.
- [ ] 3.3 Build `resources/js/admin/pages/ai/mcp-servers/{Index,Create,Edit,Form}.tsx`, following `ai/system-prompts/*` and the admin layout conventions (no gradients or box shadows):
  - The Index is a table: name, slug, URL, auth type, enabled, last synced, error, tools.
  - The Form's slug field is disabled on edit.
  - Edit shows a "Sync now" button, the tool catalog (exposed name, remote name, description, an availability chip with the reason), and a Delete button behind `useConfirmDialog`.

  Verify: `npm run build` succeeds, and the pages render in the local site at `/admin/ai/mcp-servers` (create, sync, edit, delete by hand against a reachable MCP server or a down URL).

## 4. AI system tool grants

- [x] 4.1 In `AiChatBotController::mcpTools()`, annotate each returned tool with `source` (`internal`/`remote`) and, for remote tools, `server_slug`. Look up exposed names against `AiMcpServerTool` rows of non-deleted servers. A shadowing local tool stays `internal`. Don't change the returned set. Verify: a new test in `tests/Feature/AiChatBotControllerTest.php` asserts the same names as before plus the new fields, for both `include_all=1` and `ai_system_id=` queries.
- [x] 4.2 In `AiSystemController::create()`/`edit()`, add an `mcpServers` prop (design Decision 4 shape). Verify: tests in `tests/Feature/AiMcpServerAdminTest.php` (or a new `AiSystemToolGrantsTest.php`) assert the prop on both pages, including an unavailable tool and a disabled server's `enabled: false`.
- [x] 4.3 Rework `resources/js/admin/components/AvailableMcpTools.tsx` to group into internal / per-server / "No longer available", per design Decision 4. Show unavailable catalog tools disabled with their reason, and stale selected names checked and uncheckable. Pass `mcpServers` from `ai/systems/Create.tsx` and `Edit.tsx`. Make sure the persona pages, which don't pass `mcpServers`, still render the flat list. Verify: `npx tsc --noEmit` and `npm run build` pass.
- [x] 4.4 Add a feature test showing that granting `slug__tool` through `PUT /admin/ai/systems/{id}` persists it in `allowed_tools`, and that `AiPersonaManager::availableTools(aiSystemId: …)` then returns it (seed a representable `AiMcpServerTool` on an enabled server). Also show that a stale name survives a save untouched. Verify: the test passes.
- [ ] 4.5 Manually verify in the local site: on an AI system with tools enabled, the picker shows internal tools, a group per server, a disabled unavailable tool, and a stale grant after deleting its server. Unchecking the stale grant and saving removes it.

## 5. Wrap-up

- [x] 5.1 Update `CLAUDE.md` with a short "Remote MCP servers (admin)" section: routes, the controller over `AiMcpServerManager`, write-only credentials, picker grouping, and the package-scheduled `ai:sync-mcp-servers`. Verify: the section exists and names the files.
- [ ] 5.2 Run `vendor/bin/phpunit tests/Feature/AiMcpServerAdminTest.php tests/Feature/AiChatBotControllerTest.php` plus any new test files, then ask the developer whether to run the full suite. Verify: the targeted tests pass, and the full run is either done or declined.
- [x] 5.3 Hand the developer the production steps from design.md (Migration Plan step 2) as developer-owned, without running them. Verify: they're listed in the apply summary.
