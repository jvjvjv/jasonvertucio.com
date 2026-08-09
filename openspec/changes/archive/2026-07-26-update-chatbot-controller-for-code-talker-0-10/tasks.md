## 1. Host payload services

**Two constraints apply to every subclass in this group.** The package promotes its constructor arguments as `private`, so a subclass that replaces a method cannot read `$this->urls` or `$this->modelReadiness` — it must declare its own promoted property and forward to `parent::__construct()`. And the package controller calls the page payload with named arguments, so overridden signatures must preserve parameter *names* exactly, not just types and order.

- [x] 1.1 Create `app/Services/ChatBot/` and add `RoleFilteredChatBotIndexPayload` extending `Jvjvjv\CodeTalker\Services\ChatBot\ChatBotIndexPayload`. Declare `__construct(private ChatBotRouteUrls $urls)` forwarding to `parent::__construct($urls)`, since the override needs `$urls->for()` and the parent's copy is private. Override `build(mixed $user): array` to query `App\Models\AiChatBot::query()->active()->orderBy('name')`, filter with `allowsRole($user)` (empty `allowed_roles` = public), group the user's conversations by `ai_chat_bot_id` ordered by `orderByLastMessageAtDesc()`, and emit the same per-bot keys the parent emits (`slug`, `name`, `description`, `new_chat_url`, `status_url`, `conversations` with `title`/`updated_at`/`updated_at_human`/`is_stale`). Guests get empty `conversations`.
- [x] 1.2 Add `RoleFilteredChatBotStatusResolver` extending `ChatBotStatusResolver`. Declare `__construct(private AiModelReadinessService $modelReadiness)` forwarding to the parent for the same private-property reason. Override `statusesBySlug(): array` to query `App\Models\AiChatBot` with `aiSystem` eager-loaded, filter by `allowsRole()` against the current user, and resolve `statusForSystem()` at most once per `ai_system_id`.
- [x] 1.3 Add `HostChatBotPagePayload` extending `ChatBotPagePayload`, with `__construct(ChatBotRouteUrls $urls, private Request $request)` forwarding `$urls` to the parent. This one delegates rather than replaces, so it needs no private copy of `$urls`.
- [x] 1.4 Override `HostChatBotPagePayload::build()` copying the parent signature verbatim — including the parameter names `showIdentityForm` and `includeChatHash`, which the package controller passes by name. Call `parent::build(...)`, then merge `bot.allowed_roles` (via `$aiChatBot->allowed_roles ?? []`) and a top-level `previousHref`.
- [x] 1.5 Port the referer logic into `HostChatBotPagePayload` verbatim from `ChatBotController::previousHref()`: return the `Referer` header when it is non-null, differs from the request's full URL, and its host matches `$request->getHost()`; otherwise return `route('chat-bots.index')`.

## 2. Container wiring

- [x] 2.1 In `AppServiceProvider::register()`, bind the three package payload classes to the host subclasses, alongside the existing `StoreAiChatBotRequest`/`UpdateAiChatBotRequest` bindings. Use plain `bind`, not `singleton`, so `HostChatBotPagePayload` receives the current request.
- [x] 2.2 Add a short comment above the bindings explaining why they exist (role filtering plus the `allowed_roles` / `previousHref` props that the package payloads do not know about), matching the tone of the existing form-request comment.

## 3. Retire the host controller overrides

- [x] 3.1 Reduce `app/Http/Controllers/ChatBotController.php` to `class ChatBotController extends PackageChatBotController {}` — no constructor, no actions, no `canAccess()`/`previousHref()` helpers — and drop every now-unused import.
- [x] 3.2 Grep the host app for calls to the removed package helpers (`storedState`, `putStoredState`, `storedConversation`, `historyForBot`, `routeUrlFor`, `abortIfInaccessible`, `rememberConversation`, `clearStoredState`, `requestAccessPath`, `stateKey`, `forgetLegacyCookies`) and confirm no references remain outside the package itself.

## 4. Verification

- [x] 4.1 Run `php artisan route:list --path=chats` and confirm the routes list with no `Too few arguments` constructor error.
- [x] 4.2 Run `php artisan test --compact tests/Feature/ChatBotControllerTest.php` and confirm all 14 tests pass with no assertion edits.
- [x] 4.3 Confirm `app(Jvjvjv\CodeTalker\Services\ChatBot\ChatBotPagePayload::class)` resolves to `App\Services\ChatBot\HostChatBotPagePayload` (and the same for the index payload and status resolver) via `php artisan tinker --execute`. This also proves the forwarding constructors satisfy the container — a wrong argument list surfaces here as `ArgumentCountError`.
- [x] 4.4 Load `/chats` and a bot's chat page in the browser and confirm the bot access card still renders its role label and the back link still points at the previous page — proof the `allowed_roles` and `previousHref` props survived. *(Backend side is covered by 4.6; this remains as the visual check of the React render.)*
- [x] 4.6 Close the coverage gap found during 4.2: neither `allowed_roles` (present only as factory input) nor `previousHref` (absent entirely) was asserted anywhere, so a missing binding would have left the whole suite green. Added `tests/Feature/HostChatBotPagePayloadTest.php` covering both props and all four `previousHref` branches, and confirmed all 6 fail when the `ChatBotPagePayload` binding is removed.
- [x] 4.5 Run the wider chat-related feature tests (`AiChatBotControllerTest`, `AiConversationControllerTest`, `AiChatBotOverrideTest`, `AiChatBotConversationServiceTest`) to catch fallout from the 0.10.0 upgrade beyond the controller, then ask whether to run the full suite.
