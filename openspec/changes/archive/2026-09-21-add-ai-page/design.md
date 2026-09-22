## Context

See `proposal.md` — Why. The constraints that shape the approach, verified against the current tree:

- **A document-backed content page is already an idiom here.** `LegalController` reads Markdown from `resources/legal/*.md`, renders it with `Str::markdown(..., ['html_input' => 'strip', 'allow_unsafe_links' => false])`, throws `NotFoundHttpException` when the file is missing, and hands the HTML to a shared `legal.blade.php`. Nothing new needs inventing.
- **The tool roster is reachable through public API.** `Server::createContext()` returns a `ServerContext` whose `tools()` resolves the registered classes into `Tool` instances, and `Tool::toArray()` yields the same name / description / input-schema shape `tools/list` returns over the wire. No reflection on a protected property is required.
- **`Server::__construct()` takes a `Transport`**, but `createContext()` never touches it, and the package ships `FakeTransporter` for exactly this kind of use — so the roster is readable outside a request.
- **The site has a root-level wildcard route.** `routes/codetalker-chatbots.php` registers `/{aiChatBot:slug}` and is required from `routes/web.php` *last*, with a comment stating this ordering exists so the wildcard does not swallow literal routes registered above it. Any new literal top-level path inherits that constraint.

## Goals / Non-Goals

**Goals:**

- One page that is worth a human's time on its own, and that happens to be where the endpoint is explained.
- Documentation that cannot be wrong, because it is read from the thing it documents.
- Prose the owner can revise without a deploy touching application code.

**Non-Goals:**

- Any change to the endpoint's behavior, roster, auth or limits — that is `expose-public-mcp-server` in full.
- Automated token issuance. Tokens stay hand-issued; the page says how to ask.
- A general-purpose CMS. This is one document, rendered the way the legal pages already are.

## Decisions

### Narrative as Markdown, endpoint docs as generated output

The page has two halves with genuinely different lifecycles. The prose changes when the owner's experience changes — rarely, and by them. The endpoint documentation changes when the roster changes — by a code edit, possibly by someone else, possibly years later.

Storing the prose in Blade would make every revision a code change. Storing the tool list in Markdown would guarantee it goes stale the first time a tool is added; the documentation would then be confidently wrong, which is worse than absent, because a caller would build against a tool that no longer exists.

So: prose from a document, tools from the server.

```
  resources/<doc>.md  -----> Str::markdown() -----+
                                                   |
                                                   v
  PublicServer                                  [ view ]
     |  createContext()->tools()                   |
     |  -> Tool::toArray()                         |
     +--> name / description / input schema -------+
                                                   |
                                                   v
                                                  /ai
```

*Alternative considered:* rendering the tool table by calling the endpoint's own `tools/list` over HTTP. Rejected — it makes a page render depend on the app reaching itself over the network, which fails in exactly the environments where you most want the page to work.

### Read the roster through `createContext()`, not reflection

`$server->createContext()->tools()` is public, supported API that returns resolved `Tool` instances. Reflecting on `PublicServer::$tools` would read the class strings without resolving them, leaving the page to duplicate instantiation the package already does, and would break silently if the package changed how the roster is stored.

The instances are only asked for metadata; no `handle()` runs, so nothing is authorized, cached or logged by rendering the page.

### The path must be registered before the chat-bot wildcard

`/ai` is a literal top-level path, and `routes/codetalker-chatbots.php` claims `/{aiChatBot:slug}` at the same level. The existing file comment is explicit that it is required last precisely so literal routes registered earlier win. The route therefore goes in `routes/web.php` **above** that require, alongside the other literal paths.

Consequence worth writing down: once `/ai` exists, a chat bot created with the slug `ai` would be unreachable at the root, resolving to this page instead. It would still work under `/chat/ai`. That is a acceptable, but it is the kind of thing that is baffling if discovered later without explanation.

### The owner writes the claims

The repository is rich evidence of what was built — CodeTalker on Packagist, the persona and tool-allowlist system, the resume-edit candidate and approval workflow, memory recall, provider bridging. It is not evidence of what the owner wants to claim about their own experience, which framing matters, or what they would rather not advertise.

The implementation therefore ships the page working against a stub or a draft for correction. A draft assembled from repository evidence is useful as a starting point; it is not publishable until the owner has read it.

## Risks / Trade-offs

- **Depends on `expose-public-mcp-server`.** The roster this page reads does not exist until that change lands. → Implement after it. The narrative document can be written at any time, and the page degrades to prose alone if the roster is empty.
- **A chat bot slugged `ai` would be shadowed at the root.** → Documented above and in `CLAUDE.md`; the bot remains reachable under `/chat/ai`.
- **Generated documentation is only as good as the tools' own descriptions.** A tool with a terse `#[Description]` produces a terse entry. → That is a feature: it puts pressure on the descriptions the agents read too, which are the same strings.
- **The narrative will age.** A page about AI work written once and never revisited dates itself quickly, and visibly. → It is a Markdown document specifically so revising it is cheap; no mitigation beyond that is possible.
- **Two changes both edit `llms.txt` and the homepage structured data.** → This change only adds a reference to an existing line; if it lands first, that reference has nothing to point at, which is the ordering constraint already stated.

## Migration Plan

Purely additive — a new route, a new document, a new view. Nothing existing changes behavior.

Rollback is removing the route; the discovery surfaces then reference a path that 404s, so the reference is removed with it.
