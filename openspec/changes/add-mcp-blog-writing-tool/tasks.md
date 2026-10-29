## 1. Canvas write path

- [ ] 1.1 Extract the create/update-and-publish branch of Canvas's `PostController::store()` behavior into an app-level action (e.g. `App\Actions\SaveBlogPost`) that fills the post, resolves topic and tags against existing records only (collecting unmatched slugs), syncs tags, saves, and calls `PostLifecycleEvents::dispatch($before, $post)`; verify with a feature test that publishing through it flushes the blog feed and public-MCP blog caches
- [ ] 1.2 Reuse Canvas's slug and `published_at` helpers (`PostSlug`, `PublishedAt`) rather than reimplementing them; verify a title-only call yields the same slug the editor would

## 2. The tool

- [ ] 2.1 Create `app/Mcp/Tools/SaveBlogPostTool.php` (`#[Name('save-blog-post')]`, `#[Description]`) with the input schema from design.md; verify the class is NOT under `app/Services/Mcp/Tools/`
- [ ] 2.2 Implement `shouldRegister()` to resolve `ToolContext->userId` and return true only when `hasPermissionTo('manage-blog')`; implement the same check in `handle()` returning `Response::error` otherwise; verify tool listing for anonymous, no-permission token and permitted token callers
- [ ] 2.3 Implement validation mirroring `PostRequest`: title required to publish/schedule, `schedule` requires a future timezone-qualified time, `publish` defaults to `draft`; verify each rejection scenario leaves the post unchanged
- [ ] 2.4 Implement update-by-id with Canvas's `update` gate via `Gate::forUser($user)`, reporting "not found" for both missing and forbidden posts; verify unsupplied fields are preserved
- [ ] 2.5 Return post id, slug, status, URL and an `ignored` list of unmatched topic/tag slugs; verify no topic or tag row is created

## 3. Registration and exposure

- [ ] 3.1 Add the tool to `PublicServer::$tools`; update the roster comment and `#[Instructions]` text to say the write tool is permission-gated; verify the anonymous `tools/list` is byte-identical to before
- [ ] 3.2 Confirm the tool does not use `CachesPublicToolResults`, and that `McpCallLogger` stores no arguments; verify with a test that a call record for a save contains no part of the body
- [ ] 3.3 Decide the throttle question from design.md (dedicated per-token write limit vs. existing tier) and implement or record the follow-up

## 4. Tests

- [ ] 4.1 Feature tests for visibility and authorization across anonymous, token-without-`manage-blog`, and token-with-`manage-blog` callers (list and call-by-name)
- [ ] 4.2 Feature tests for create-as-draft, update, publish now, schedule, and the validation failures; for existing/unknown topic and tag slugs
- [ ] 4.3 Feature test that an invalid token is still rejected with 401 and that a revoked token cannot write
- [ ] 4.4 Update the existing roster tests that assert exactly three tools so they still pass for anonymous callers and add the permitted-caller case

## 5. Documentation

- [ ] 5.1 Amend `.claude/context/mcp.md`: replace "Never add a tool that writes" with the permission-gated exception, add the tool to the roster table, and record the unscoped-token risk
- [ ] 5.2 Update `public/llms.txt` and `CLAUDE.md` where they describe the endpoint as read-only
- [ ] 5.3 Run the MCP and blog test files (`vendor/bin/phpunit --filter 'Mcp|Blog'`) and confirm they pass
