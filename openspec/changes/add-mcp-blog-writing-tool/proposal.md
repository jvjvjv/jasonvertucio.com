## Why

Blog posts can only be written through the Canvas editor in a browser. An author who already works with an MCP-capable agent has no way to hand it a draft and have it land as a post. The public MCP endpoint already resolves a bearer token to a user and evaluates permissions against that user, so a post-writing tool can be offered to exactly the callers who hold `manage-blog` without a new server.

## What Changes

- Add one MCP tool, `save-blog-post`, on the existing `/mcp` endpoint. It creates a Canvas post, or updates one by id, and can optionally publish or schedule it.
- The tool is **hidden from `tools/list` and refuses invocation** unless the caller presented a valid token whose user holds `manage-blog`. Anonymous callers and token holders without that permission see the same three-tool roster as today.
- Writes go through the same path as the Canvas editor (slug generation, topic/tag resolution against existing records, lifecycle events), so the public blog caches and the blog feed flush exactly as they do for a post saved in the browser.
- **BREAKING (documented rule):** the public MCP roster has been read-only by rule ("never add a tool that writes"). That rule is amended: the roster is read-only for every caller *except* a holder of `manage-blog`, for this one tool. Resume editing, candidate approval and targeted resumes stay unreachable.
- Calls to the tool are recorded in `mcp_calls` like any other, without storing the post body.

## Capabilities

### New Capabilities
- `mcp-blog-post-authoring`: the `save-blog-post` tool — who may see and call it, what it creates or updates, publish/schedule behavior, validation, and cache/feed consistency.

### Modified Capabilities
- `public-mcp-server`: the "exactly one curated read-only roster" requirement changes to allow a single permission-gated write tool while keeping the anonymous roster unchanged.

## Impact

- New: `app/Mcp/Tools/SaveBlogPostTool.php` (lives under `app/Mcp/`, not `app/Services/Mcp/Tools/`, so chat-bot discovery never registers it), plus a small service or action class for the Canvas write if one is needed to share logic with the controller path.
- `app/Mcp/Servers/PublicServer.php` — roster gains the tool; its instructions and the "audit surface" comment are updated.
- `.claude/context/mcp.md`, `public/llms.txt`, and the server `Instructions` — the "read-only / never add a tool that writes" wording is amended.
- `config/mcp-server.php` — no new key expected; a tighter write throttle may be added (see design).
- Tests: tool visibility by caller tier, authorization refusal, create/update/publish/schedule, cache flush, call-log contents.
- No schema changes. No dependency changes.
