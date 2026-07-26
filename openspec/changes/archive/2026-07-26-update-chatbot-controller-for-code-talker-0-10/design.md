## Context

`App\Http\Controllers\ChatBotController` extends the package's `ChatBotController` and overrides four actions (`index`, `show`, `showByHash`, `statuses`). Those overrides were written against a controller that did the work inline and exposed `protected` helpers for subclasses to lean on.

code-talker 0.10.0 moved that work into ten constructor-injected collaborators and deleted the helpers. Two things break at once:

1. The host constructor calls `parent::__construct($conversationService, $readinessService)` — the parent now takes ten arguments, so nothing in the chat-bot routes can boot.
2. Every override body calls at least one removed helper (`abortIfInaccessible`, `storedConversation`, `historyForBot`, `routeUrlFor`, `storedState`, `putStoredState`).

Comparing the host overrides against the new package services shows the overlap is now almost total. `ChatBotIndexPayload` reproduces the host `index` payload verbatim. `ChatBotPagePayload` reproduces the host `show`/`showByHash` payloads including `total_cost_usd`, which the host previously added by hand. `ChatBotStatusResolver` reproduces the host `statuses` per-`AiSystem` caching.

Exactly three host-only behaviors are left over:

| Behavior | Where it lives today | Package equivalent |
| --- | --- | --- |
| Filter index + statuses by `allowsRole()` | `ChatBotController::canAccess()` | none — package lists all active bots |
| `bot.allowed_roles` page prop | `show`/`showByHash` arrays | none |
| `previousHref` page prop | `ChatBotController::previousHref()` | none |

`allowed_roles` and `allowsRole()` are host concepts entirely: they live on `App\Models\AiChatBot`, not on the package model. Per-bot authorization (the 403 on a restricted bot) is already enforced by `App\Http\Middleware\CheckChatBotAccess` on the route groups, so the controller's remaining role work is list *filtering* only.

## Goals / Non-Goals

**Goals:**

- Get the chat-bot routes booting again on code-talker 0.10.0.
- Keep every prop the React pages consume identical in name and shape, so no frontend change is needed.
- Keep `tests/Feature/ChatBotControllerTest.php` passing with zero assertion edits.
- Leave the host with the smallest possible surface pointed at package internals, so the next package refactor has less to break.

**Non-Goals:**

- Changing route definitions, middleware, or `routes/codetalker-chatbots.php`.
- Changing `CheckChatBotAccess` or moving role authorization.
- Pushing `allowed_roles` upstream into the package.
- Touching `resources/js/` or any Inertia page component.
- Touching the other host subclasses of package controllers (`Admin\AiChatBotController`, `Admin\AiToolsController`).

## Decisions

### Swap container-bound payload services instead of overriding controller actions

The package's own migration note points here, and it is also the smaller change. The three host behaviors are all payload shaping, and payloads are exactly what the new services own.

Three host subclasses under `app/Services/ChatBot/`:

- `App\Services\ChatBot\RoleFilteredChatBotIndexPayload extends ChatBotIndexPayload`
- `App\Services\ChatBot\HostChatBotPagePayload extends ChatBotPagePayload`
- `App\Services\ChatBot\RoleFilteredChatBotStatusResolver extends ChatBotStatusResolver`

Bound in `AppServiceProvider::register()`:

```php
$this->app->bind(BaseChatBotIndexPayload::class, RoleFilteredChatBotIndexPayload::class);
$this->app->bind(BaseChatBotPagePayload::class, HostChatBotPagePayload::class);
$this->app->bind(BaseChatBotStatusResolver::class, RoleFilteredChatBotStatusResolver::class);
```

The package controller type-hints the base classes, so the container hands it the host subclasses — the same pattern `AppServiceProvider` already uses for `StoreAiChatBotRequest`/`UpdateAiChatBotRequest`. Bindings go in `register()` next to those, keeping all package-swap wiring in one place.

None of the three classes is `final`, and none is registered in `CodeTalkerServiceProvider` — they are zero-config autowired, so a `bind()` is the whole wiring, and they are not singletons.

### Subclasses must re-declare the collaborators they need

The package promotes its constructor arguments as `private`:

```php
class ChatBotIndexPayload
{
    public function __construct(private ChatBotRouteUrls $urls) {}
```

`private` is invisible to subclasses. A host subclass that overrides `build()` outright therefore cannot use `$this->urls`, and `RoleFilteredChatBotStatusResolver` cannot use `$this->modelReadiness`. Each override-in-full subclass must declare its own promoted property and forward to the parent constructor:

```php
public function __construct(private ChatBotRouteUrls $urls)
{
    parent::__construct($urls);
}
```

`HostChatBotPagePayload` is the exception — it delegates to `parent::build()` rather than replacing it, so it never touches the parent's private state and only needs to forward `$urls` while adding its own `Request`.

### Overrides must preserve parameter names, not just types

The package controller calls the page payload with named arguments:

```php
$this->pagePayload->build($bot, $conversation, $history, showIdentityForm: ..., includeChatHash: true);
```

PHP resolves named arguments against the *called* class's signature, so `HostChatBotPagePayload::build()` must keep the parameter names `showIdentityForm` and `includeChatHash` exactly. Renaming them — even with identical types and order — throws `Unknown named parameter`. The same caution applies to `build(mixed $user)` on the index payload if the package ever calls it by name.

*Alternative considered:* keep overriding the controller actions and give the host controller a ten-argument constructor that forwards to `parent::__construct()`. Rejected — the package's promoted constructor properties are `private`, so the subclass would have to re-declare and store its own copies of every collaborator it uses, and it would re-break on any future constructor change. The whole point of 0.10.0 is that the collaborators are the extension seam.

### `App\Http\Controllers\ChatBotController` becomes an empty subclass

`routes/codetalker-chatbots.php` is a published file that names `App\Http\Controllers\ChatBotController` in eleven places. Keeping the class — with no constructor and no method bodies — preserves the route file untouched and leaves an obvious place for future host-only actions.

*Alternative considered:* delete the class and repoint the route file at the package controller. Rejected as churn with no benefit; the published route file is deliberately host-owned.

### Role filtering re-queries with `App\Models\AiChatBot`

`ChatBotIndexPayload::build()` and `ChatBotStatusResolver::statusesBySlug()` query `Jvjvjv\CodeTalker\Models\AiChatBot`, whose instances have no `allowsRole()`. The host subclasses therefore override `build()` / `statusesBySlug()` outright and run the same query against `App\Models\AiChatBot`, filtering with `allowsRole()` before shaping.

This duplicates a modest amount of package query and mapping code. That is accepted: the duplicated logic is stable (name ordering, conversation grouping, per-system status caching) and the alternative — filtering the package's already-built array by re-looking-up each bot — would be both slower and more fragile.

*Alternative considered:* bind `Jvjvjv\CodeTalker\Models\AiChatBot` to the host model. The package has no model-override config and its services reference the class name statically, so there is no supported hook.

### `previousHref` and `allowed_roles` are added in `HostChatBotPagePayload::build()`

`build()` keeps the parent signature, calls `parent::build(...)`, then merges the two extra keys. `allowed_roles` reads from the bot passed in; when that is a package-model instance it may lack the attribute, so it is read defensively via `$aiChatBot->allowed_roles ?? []`, matching what the current controller does.

`previousHref` needs the request. The payload services are container-resolved per use (plain `bind`, not `singleton`), so `HostChatBotPagePayload` constructor-injects `Illuminate\Http\Request` alongside the `ChatBotRouteUrls` the parent requires. The referer logic moves over verbatim from `ChatBotController::previousHref()`.

*Alternative considered:* share `previousHref` from `HandleChatInertiaRequests` middleware. Rejected — it would apply to every chat Inertia response, not just the two chat-bot pages, and the spec ties the prop to the `ai/ChatBot` page.

### `App\Models\AiConversation` is not needed by the payloads

The host `index` override used `App\Models\AiConversation`, but only for `orderByLastMessageAtDesc()`, `title`, and `is_stale` — all package-level. The host subclass adds nothing there, so it can use whichever model the package query returns. `App\Models\AiConversation`'s host-only `targetedResume()` relation is unrelated to the chat pages.

## Risks / Trade-offs

- **The host duplicates package query/mapping code in two payload subclasses, and can drift when the package changes its payload shape.** → The duplication is confined to two `build`-style methods and the existing feature tests assert the resulting prop shape, so drift surfaces as a test failure rather than a silent regression.
- **`ChatBotPagePayload` could become a `singleton` in a future package release, which would freeze the injected `Request`.** → It is currently unregistered and autowired per resolution, so this is not a present concern. Injecting `Request` (rather than resolving it lazily) makes such a change fail visibly in the `previousHref` tests instead of quietly serving a stale referer.
- **The package's `private` constructor properties mean any future collaborator added to a parent constructor silently fails to reach a host subclass that forwards a fixed argument list.** → The forwarding constructors are one line each and a signature mismatch is a hard `ArgumentCountError` at resolution time, caught by the first test that renders a chat page.
- **This change is forward-only: the resulting host code cannot run against code-talker 0.9.x.** → Acceptable — the pre-change host code cannot run against 0.10.0 either, so the two are simply pinned to each other. A rollback must revert the package pin alongside the host files.
- **Nothing in the host currently asserts that the bindings are wired.** → The spec's binding scenario plus the existing index/statuses role-filtering tests cover it end to end; a missing binding drops role filtering and fails those tests immediately.
- **The package may later grow its own role concept, duplicating `allowed_roles`.** → Out of scope here; the host subclasses are small enough to delete if that lands.

## Migration Plan

1. Add the three payload subclasses.
2. Add the bindings.
3. Gut the host controller.
4. Verify `php artisan route:list --path=chats` resolves, then run `tests/Feature/ChatBotControllerTest.php`.

No database, config, or frontend migration. Rollback is a revert of the three files — but note the pre-change state does not run against code-talker 0.10.0 at all, so rolling back requires pinning the package back to 0.9.x.

## Open Questions

None. The package's extension seam, the host-only behaviors, and the acceptance surface are all established.
