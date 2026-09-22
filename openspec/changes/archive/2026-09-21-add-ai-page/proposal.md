## Why

The `expose-public-mcp-server` change publishes a query endpoint at `/mcp` and two machine-facing descriptions pointing at it, but nothing a human can read. An agent's operator who follows the reference lands on `POST /mcp`, which answers `GET` with `405 Allow: POST` — correct protocol conformance, useless as an explanation. There is no page that says what the endpoint is, what it serves, or how to get a token.

Separately, the site's portfolio predates most of what is now the most substantial work in the repository. CodeTalker is extracted and published to Packagist; there is a multi-persona chat system with per-system tool allowlists and permission-gated bots, an MCP tool suite spanning resume editing, targeted resumes and cover letters, an AI-drafted-revision workflow with a human approval gate and versioned publishing, per-user memory recall, and provider bridging across hosted and local models. None of that is visible to a visitor.

One page answers both: a human-readable home for the AI work that also documents the endpoint.

## What Changes

- Add a `/ai` page presenting the site owner's AI work and documenting the public MCP endpoint.
- **Write the narrative as a Markdown document**, following the existing `LegalController` pattern — content lives in a file under `resources/`, rendered through a shared view — so the prose is editable without touching Blade.
- **Generate the endpoint documentation from the server's own tool roster.** Each registered tool already carries its name, description and input schema — the same metadata `tools/list` returns over the wire. Rendering the page from that roster means the documentation cannot drift from the server, and a tool added later documents itself.
- Reference the page from the discovery surfaces the other change publishes, so `llms.txt` and the homepage's structured data point at a human-readable explanation rather than only at a protocol endpoint.

**Non-goals:** no change to the endpoint, its tools, its auth or its rate limits — all of that is `expose-public-mcp-server`'s. No public MCP registry listing; this page is the self-hosted equivalent, and a registry entry added later would point at it. No token self-service — tokens stay hand-issued, and the page says how to ask.

## Capabilities

### New Capabilities

- `ai-work-page`: a public page describing the site owner's AI work and documenting the public query endpoint, where the endpoint's documentation is derived from the server rather than maintained by hand.

### Modified Capabilities

- `machine-readable-discovery`: the machine-facing descriptions gain a reference to the human-readable page, so an agent can hand its operator somewhere to read.

## Impact

**Depends on** `expose-public-mcp-server`. The page documents that change's endpoint and reads its tool roster; the capability it modifies is introduced there. It should be implemented after, though the narrative content can be written at any time.

**Code**

- New route and controller for `/ai`, following `LegalController`'s Markdown-rendering shape
- New Markdown content document under `resources/`
- A view rendering the narrative plus the generated tool table
- A reader that turns the MCP server's registered tools into displayable documentation
- `public/llms.txt` and the homepage structured data — add the page reference

**Content**

- The narrative is the site owner's to write or approve. It states claims about their experience, and no amount of repository evidence makes those claims ours to assert.

**Operations**

- None beyond an ordinary deploy. The page is public and unauthenticated, like the rest of the site's content pages.
