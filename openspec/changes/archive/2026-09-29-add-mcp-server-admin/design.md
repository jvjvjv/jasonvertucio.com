## Context

See proposal.md for the motivation. What the package (code-talker 0.16.x) already provides, as observed in `vendor/jvjvjv/code-talker`:

- **Models**: `AiMcpServer` (soft-deletes, `auth` cast `encrypted:array` and `$hidden`) and `AiMcpServerTool` (`exposed_name`, `remote_name`, `title`, `description`, `representable`, `unrepresentable_reason`, `synced_at`). The migrations load through the package's `loadMigrationsFrom()`; locally both are **Pending**.
- **`AiMcpServerManager`**:
  - `createRules()` / `updateRules()` are static and validate the slug pattern and uniqueness, `url:http,https`, and the `auth` shape per type. `updateRules()` has no slug rule, and `update()` discards a submitted slug.
  - `create()` / `update()` validate internally, write, then `sync()`. They return `['server', 'sync' => ['server', 'stored', 'unrepresentable', 'error']]` and never throw on sync failure.
  - `update()` keeps the stored `auth` when the `auth` key is **omitted**.
  - `delete()` soft-deletes and returns the number of referencing systems.
  - `list()` returns servers with their tool catalogs and `auth_type`, never `auth` itself.
- **Auth keys read by the client**: `type`, `token`, `headers`, `client_id`, `client_secret`, `scope`, `token_endpoint`.
- **`ChatBotToolRegistry`**: with `exposeAllDiscoveredTools` it lists local tools plus every *representable* tool of *enabled* servers (`RemoteToolCatalog::handlers(null)`). A local tool wins a name collision.
- **Scheduling**: the package already schedules `ai:sync-mcp-servers` daily at 03:30.

The host side today:
- `routes/codetalker-admin.php` groups `/admin/ai` under `web, auth, can:manage-ai-tools`.
- Controllers such as `AiSystemPromptController` wrap a package manager, with form requests that return the manager's static rules.
- The AI system create and edit pages render `AvailableMcpTools`, a flat checkbox list fed by `GET /api/admin/ai/personas/mcp-tools?include_all=1` (`AiChatBotController::mcpTools()` → `AiPersonaManager::availableTools()`), and store the selection in `allowed_tools`. So remote tools would already show up *flat and unlabelled* once a server exists. There's just no way to create one.
- The admin navigation is `resources/js/admin/navigation.json`, filtered by `can`.

## Goals / Non-Goals

**Goals:**
- CRUD, sync and catalog screens for MCP servers, built on `AiMcpServerManager` with no validation duplicated.
- One picker for internal and remote tools on AI systems, grouped by source, surfacing unavailable tools and stale grants.

**Non-Goals:**
- stdio transport or interactive OAuth. The package doesn't support them.
- Per-persona tool selection. Grants stay at the AI system level, as today.
- Restricting server URLs to public hosts. See Risks.
- UI for the `RemoteMcpToolDefinitionChanged` event. Logging or notifying on it can come later.
- Changing the persona admin page's tool display beyond what the shared component change gives it for free.

## Decisions

**1. A thin host controller over `AiMcpServerManager`.**
`Admin\AiMcpServerController` has `index`, `create`, `store`, `edit`, `update`, `sync` and `destroy`. `StoreAiMcpServerRequest` returns `AiMcpServerManager::createRules()` and `UpdateAiMcpServerRequest` returns `updateRules()`. The manager re-validates inside `create()`/`update()`, but running the rules in a form request is what gives Inertia field-level errors and a redirect back.
*Alternative*: validate only through the manager and catch its `ValidationException`. Rejected: it diverges from every sibling controller for no gain.

Routes go under the existing `/admin/ai` group: `mcp-servers.index|create|store|edit|update|sync|destroy`, with `POST /mcp-servers/{aiMcpServer}/sync`. The literal `new` route is registered before `{aiMcpServer}`. The route model binding resolves `Jvjvjv\CodeTalker\Models\AiMcpServer` directly; there's no host subclass. Soft-deleted servers 404 by default.

**2. Sync outcome as flash, not an exception.**
`store`, `update` and `sync` all get a report back. With no error, flash `success` ("Synced N tools, M unavailable"). With an error, flash `error` ("Saved, but sync failed: …"). `store` redirects to the new server's edit page so the catalog is visible immediately. `update` and `sync` redirect back to edit.

**3. Credentials are write-only in the form.**
The edit page's props come from `list()`-shaped data, which has `auth_type` but never `auth`. The auth editor sends `auth` **only when the administrator has changed it**, tracked by a dirty flag. Otherwise the key is omitted from the request, which is exactly the case where `update()` keeps the stored credentials. Switching to type `none` sends `{"type":"none"}`, which replaces them. Secret inputs render empty with the placeholder "Stored — leave blank to keep". Editing any secret field marks auth dirty, and the whole auth object then has to be re-entered. That's simpler and safer than merging partial secrets server-side.
*Alternative*: merge submitted fields over the decrypted stored auth. Rejected: it adds host-side secret handling the package deliberately avoided.

The `auth` value is sent as an object. The manager accepts either an array or JSON. Headers are edited as a key/value row list, mirroring how `WebToolPolicyEditor` treats structured JSON.

**4. The picker gets structure from page props, and membership from the existing endpoint.**
- AI system `create` and `edit` gain an `mcpServers` prop: `AiMcpServerManager::list()` reduced to `{id, slug, name, enabled, tools: [{exposed_name, description, available, unavailable_reason}]}`.
- `AiChatBotController::mcpTools()` keeps returning the same tool set. Each entry gains `source` (`internal` | `remote`) and, for remote, `server_slug`. The source is determined by looking up the name among `AiMcpServerTool::exposed_name` rows of non-deleted servers, but only when the registry returned it as a remote handler. A local tool that shadows a remote name stays `internal`. That's additive, which satisfies the compatibility requirement.
- `AvailableMcpTools` (retitled "Available Tools" when `includeAllTools`) renders:
  1. internal tools;
  2. one group per enabled server, with grantable tools as checkboxes and unavailable catalog tools from `mcpServers` disabled with their reason;
  3. a "No longer available" group for any selected name that appears in neither the endpoint result nor any catalog, checked and uncheckable.

  Disabled servers' tools don't appear (the registry already excludes them). A stale grant pointing at a disabled server shows in group 3, labelled "server disabled".

  **Layout (revised at the developer's request):** groups 1 and 2 sit behind "Built-in" and "External" tabs, each labelled with its granted/grantable count. Inside "External", up to two servers stack as labelled groups. From three servers on, they become a vertical tab list, one tab per server, also with counts. Group 3 renders below the tabs, never inside one, so a stale grant can't hide behind the unselected tab. Persona pages don't pass `mcpServers` and keep the flat list.

*Alternative*: a separate remote-tool picker component. Rejected: the request is explicitly "just like the internal tools", and one list with one `allowed_tools` array keeps a single source of truth.

**5. Navigation.**
Add `{ can: "manage-ai-tools", href: "/admin/ai/mcp-servers", icon: "Hub", label: "MCP Servers", description: "Connect external MCP servers and review their tools" }` after "System Prompts" in `navigation.json`. Confirm the icon name is in the admin icon map during implementation, falling back to an existing one.

**6. The dependency bump is lockfile-only.**
Run `composer update jvjvjv/code-talker` to reach 0.16.1. `composer.json`'s `^0` is unchanged. The 0.16.1 release changes only the README, so there's no code impact.

## Risks / Trade-offs

- [SSRF: an admin-entered URL makes the server issue requests (sync at save time, calls during turns) to any host, including private addresses] → It's limited to `manage-ai-tools` holders, who can already point `AiSystem.base_url` at arbitrary hosts, so this adds no new trust boundary. It's documented here rather than restricted. Local-network MCP servers are a legitimate use.
- [Save blocks on a sync to a slow server] → The sync is bounded by the server's effective timeout (≤ `remote_mcp.max_timeout_seconds`, 30s). Acceptable for an admin action. The form disables the submit button while processing.
- [Tool calls during a synchronous SSE turn have no heartbeat (package known issue)] → Leave `remote_mcp.max_timeout_seconds` at its default. No host change.
- [Stale grants accumulate after a server is deleted] → They're shown in the picker (Decision 4) so they can be cleaned up. The package ignores unknown names at runtime.
- [The container's separate `vendor/` volume is still on 0.16.0] → Tasks include updating inside `jv-app` too.

## Migration Plan

1. Locally: `composer update jvjvjv/code-talker`, then the same inside `jv-app`. Then run `php artisan migrate` and `DB_DATABASE=wink php artisan migrate` (the test DB).
2. Production (developer-owned; never run by the agent): deploy, run `composer install` and `php artisan migrate`, restart the queue worker via `supervisorctl`, and confirm the scheduler is running so the 03:30 `ai:sync-mcp-servers` fires.
3. Rollback: revert the code. The two tables can stay; they're inert without servers.
