## 1. Prerequisites

- [x] 1.1 Confirm `expose-public-mcp-server` is implemented far enough that `PublicServer` exists with its tool roster populated; verify `php artisan mcp:inspector` lists the three tools

## 2. Route and page shell

- [x] 2.1 Add the `/ai` route in `routes/web.php` **above** the `routes/codetalker-chatbots.php` require, so the root-level `/{aiChatBot:slug}` wildcard does not claim it; verify `php artisan route:list --path=ai` shows the literal route and that requesting `/ai` does not resolve to a chat bot
- [x] 2.2 Create the controller following `LegalController`'s shape — read the Markdown document, render with `html_input => strip` and `allow_unsafe_links => false`, throw `NotFoundHttpException` when the file is absent; verify a test asserting a missing document returns 404 rather than a 500
- [x] 2.3 Create the view extending the site layout, rendering the narrative above the generated tool documentation; verify the page returns 200 for an unauthenticated visitor

## 3. Generated endpoint documentation

- [x] 3.1 Write the roster reader: construct `PublicServer` with the package's `FakeTransporter`, call `createContext()->tools()`, and map each `Tool::toArray()` to displayable name, description and arguments; verify a unit test asserts all three registered tools come back
- [x] 3.2 Render the tool documentation in the view and verify a feature test asserts each tool's name and description appears on the page
- [x] 3.3 Verify the documentation tracks the roster: a test that registers an extra tool on a test double server and asserts it appears without editing the view or the narrative document
- [x] 3.4 Verify a tool removed from the roster disappears from the page
- [x] 3.5 Confirm rendering the page runs no tool handler — assert no call-log row is written by a page request, once `expose-public-mcp-server`'s logging exists

## 4. Narrative content

- [x] 4.1 Create the Markdown document under `resources/` with the sections the page needs, and verify editing it changes the rendered page with no code change
- [x] 4.2 Draft the narrative from repository evidence — CodeTalker on Packagist, the persona and per-system tool-allowlist model, the MCP tool suite, the resume candidate/approval workflow, memory recall, hosted and local provider bridging — and hand it to the developer for correction. **Developer-owned:** the claims are theirs; do not publish an undrafted or uncorrected version
- [x] 4.3 Write the endpoint section of the document: URL and transport, that public data needs no credentials, what a token additionally yields, and how to request one; verify a test asserts the page carries no token or credential string

## 5. Discovery references

- [x] 5.1 Add the page's URL to `public/llms.txt` alongside the endpoint, and verify a test asserts both URLs appear
- [x] 5.2 Relate the owner to the page in the homepage structured data, and verify a test asserts the reference is present and that the block still carries no email or telephone

## 6. Documentation

- [x] 6.1 Update `CLAUDE.md`: the `/ai` page, its document-backed content pattern, that its tool table is generated from `PublicServer`, and the route-ordering constraint against the chat-bot wildcard — including that a bot slugged `ai` is shadowed at the root and remains reachable at `/chat/ai`
