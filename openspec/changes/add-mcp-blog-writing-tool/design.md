## Context

`/mcp` (`routes/ai.php`, `App\Mcp\Servers\PublicServer`) serves a read-only roster to anonymous callers and resolves an optional bearer token through `AuthenticateMcpCaller`, which calls `Auth::shouldUse('sanctum')` for a valid token and rejects an unresolvable one with 401. Tools read the caller through CodeTalker's `ToolContext` (`$context->userId`); `CompanyIntelTool` is the precedent for a tool that hides itself with `shouldRegister()` and re-checks in the handler, using `hasPermissionTo()` rather than `can()` because Keystone registers no gates outside HTTP.

Canvas v7 posts live in `canvas_posts` (`slug`, `title`, `summary`, `body`, `published_at`, `topic_id`, `user_id`, `pending`, soft deletes) with `tags` and `topic` relations. `Canvas\Http\Controllers\PostController::store()` is the reference write path: it fills the post, clears `pending`, resolves topic and tags **against existing records only** (unknown slugs are ignored), syncs tags, and calls `PostLifecycleEvents::dispatch($before, $post)`, which emits `PostPublished`/`PostUpdated`/`PostUnpublished`/etc. The app already hangs `FlushBlogFeedCache` and `FlushPublicMcpBlogCache` on those events. Post bodies are rendered unescaped (`{!! $post['body'] !!}` in `blog/single.blade.php`), so the body is HTML written by a trusted author.

`.claude/context/mcp.md` and the `public-mcp-server` spec currently state that every tool on the roster is read-only and that write tools must never be added. The user has decided to put this tool on the public endpoint anyway; this change amends that rule narrowly rather than ignoring it.

## Goals / Non-Goals

**Goals:**
- One tool that creates or updates a post, with optional publish/schedule, for `manage-blog` holders only.
- The anonymous and non-permitted roster and responses are unchanged.
- A post written through the tool is indistinguishable from one saved in the Canvas editor: same events, same cache flushes, same slug rules.

**Non-Goals:**
- Deleting or unpublishing posts, managing topics/tags (creating new ones), uploading media/featured images, or reading drafts back (a separate read tool can follow).
- Moving the endpoint, adding a second MCP server, or exposing any other write tool.
- Scoping tokens by ability. Tokens remain unscoped; see Risks.

## Decisions

**Tool shape.** A single `save-blog-post` tool. Inputs: `id` (optional ULID/UUID of an existing post; omitted → create), `title`, `body` (HTML, as Canvas stores it), `summary`, `slug` (optional), `topic` (slug of an existing topic), `tags` (slugs of existing tags), and `publish` (`draft` default | `now` | `schedule`) with `published_at` (ISO-8601 with timezone, required for `schedule`). One tool covers create and update because Canvas's own endpoint is an upsert and it keeps the roster addition to a single line. Alternative: separate create/update/publish tools — rejected as three audit-surface entries for one capability.

**Draft by default; publishing is explicit.** `publish` defaults to `draft`. The user chose "drafts plus publish", so publish/schedule are supported, but an omitted parameter must never put content live. Validation mirrors `PostRequest`: publishing requires a title; `schedule` requires a timezone-qualified future time.

**Authorization: hide and re-check.** `shouldRegister()` returns true only when `ToolContext->userId` resolves to a user for whom `hasPermissionTo('manage-blog')` holds, so `tools/list` for everyone else is byte-identical to today. `handle()` re-checks and returns `Response::error` otherwise, in case a client calls the name directly. This reuses the `CompanyIntelTool` pattern and the existing "permissions evaluated against the token's user" contract.

**Write path.** Extract the body of `PostController::store()`'s non-pending branch into (or call through) a small action so the tool and the controller cannot drift; the tool never uses `writePending()` — an MCP save is a deliberate save, not an editor autosave. Updating an existing post additionally requires Canvas's `update` gate for the user via `Gate::forUser($user)->allows('update', $post)`; a refusal is reported as "post not found", matching the controller's behavior of not confirming existence. Topic and tags resolve against existing records; unknown slugs are reported back in the result as `ignored`, not silently dropped and not created.

**Events and caches.** `PostLifecycleEvents::dispatch($before, $post)` is called after save so the existing listeners flush the blog feed and the public-MCP blog cache. No new cache logic.

**Not discoverable by chat bots.** The class lives in `app/Mcp/Tools/`, not `app/Services/Mcp/Tools/`, because that tree is registered with CodeTalker's discovery and would hand the write tool to the chat loop.

**Logging.** `McpCallLogger` records the method and tool name from `params.name`, not arguments, so bodies are not stored. The result returned to the caller includes the post id, slug, status and URL.

**Throttle.** Token holders get 120/min and 10,000/day on `/mcp`. A write tool warrants a lower ceiling; add a dedicated per-token limit for `tools/call` of this tool only if the existing limiter can express it cheaply, otherwise note it as a follow-up. Open question below.

## Risks / Trade-offs

- **[Risk] Tokens are unscoped.** Any token minted for a user who holds `manage-blog` can now publish. A leaked token that was previously only able to read a resume can write to the live blog. → Mitigation: the tool is permission-gated per user, defaults to draft, every call is logged with the resolved user; recommend a follow-up to scope Sanctum token abilities (e.g. `blog:write`) before wide token distribution.
- **[Risk] Body is rendered unescaped.** The tool will store whatever HTML an agent supplies, the same trust the Canvas editor extends to an author. → Accepted for parity; a prompt-injected agent holding a `manage-blog` token could publish script. Sanitization is out of scope but flagged for review.
- **[Trade-off] Amending the read-only rule.** The public roster's audit value ("everything here is safe to call anonymously") weakens to "everything visible anonymously is read-only". → Mitigation: the anonymous `tools/list` is unchanged and asserted by test; docs and `llms.txt` say so explicitly.
- **[Risk] Shared cache keys.** Public tool results are cached per disclosure level; the write tool must never be cached (writes must not be served from cache) and must not alter other callers' cache keys. → It does not use `CachesPublicToolResults`.

## Migration Plan

1. Ship code, tests and doc amendments together; no migrations or config required.
2. After deploy, restart nothing special (no queued work added). Existing tokens keep working; users without `manage-blog` see no change.
3. Rollback: remove the tool from `PublicServer::$tools`; posts it created are ordinary Canvas posts and need no cleanup.

## Open Questions

- Should the write tool have its own stricter throttle, or is the existing token tier acceptable for now?
- Should `body` accept Markdown and convert to HTML server-side, since agents write Markdown more naturally than HTML? (Default for this change: HTML only, matching Canvas.)
