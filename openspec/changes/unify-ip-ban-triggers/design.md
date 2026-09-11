## Context

See `proposal.md` - Why. Today `IpBan` (table `ip_ban`, fillable `ip`, `banned_method`, `banned_url`, `banned_body`) has exactly one producer: `WordpressController::ban()`, called from the honeypot routes in `routes/honeypots.php`, which does `IpBan::firstOrCreate([...])` and then manually refreshes the `banned_ip_list` cache that `IpMiddleware` reads. Two more producers are planned: a future spam-escalation listener (`escalate-repeat-spam-to-ipban`, not built yet) and a new manual admin action from `Admin/CommentModerationController`. `IpMiddleware` only *reads* `IpBan` to block requests; it never creates rows, so it is unaffected by this design.

The codebase already has one precedent for "one model, one lifecycle hook, one downstream reaction fired from many producers": `Comment` → `CommentObserver::created()` → queued `CommentReceivedMail`. It also has a precedent for a plain domain event with framework-level (not domain) semantics: Canvas's `PostPublished`/`PostUpdated`/etc., wired via `Event::listen([...], FlushBlogFeedCache::class)` in `AppServiceProvider::boot()`. This design combines both: an **observer** guarantees the event fires no matter which producer created the row (the "one true place" problem an observer solves), and a **plain event class** carries the fan-in semantics that a raw model-observer callback can't express well (multiple independent listeners, a payload richer than the Eloquent model, testability via `Event::fake()`).

## Goals / Non-Goals

**Goals:**
- Guarantee `IpBanned` fires for every `IpBan` row creation, present and future, without each producer remembering to dispatch it.
- Keep the three producers (honeypot, future spam-escalation, manual admin) fully decoupled from the alerting logic.
- Keep the manual admin ban action minimal: reuse the comment's existing `ip_address` column, no new schema.

**Non-Goals:**
- Building the actual Slack/webhook integration - `SendIpBanAlert` only stubs the hook (e.g. a method left as a no-op with a `// TODO` or a config-gated early return) and only reliably implements the structured log line.
- Building `escalate-repeat-spam-to-ipban` itself - this design only guarantees that whenever *that* change lands and calls `IpBan::create()`/`firstOrCreate()`, it gets `IpBanned` for free.
- Changing `IpMiddleware`'s read path or the `banned_ip_list` cache invalidation strategy - out of scope, no observed behavior change needed there.
- Rate-limiting or deduplicating alerts - `firstOrCreate` already prevents duplicate rows (and thus duplicate events) for an IP banned twice via the same producer's own idempotency check; cross-producer deduplication (e.g. honeypot and manual ban racing on the same IP) is not addressed here.

## Decisions

### Dispatch from an `IpBan::created()` model observer, not from each producer
Alternative considered: have `WordpressController::ban()`, the future spam-escalation listener, and the new admin action each call `event(new IpBanned(...))` explicitly after creating the row.

Rejected because it reintroduces exactly the duplication problem the proposal exists to solve - three call sites that must all remember to fire the event identically, and any future fourth producer inherits the same obligation. Centralizing in an `IpBanObserver::created()` hook (registered in `AppServiceProvider::boot()` via `IpBan::observe(IpBanObserver::class)`, mirroring the existing `Comment::observe(CommentObserver::class)` line) means the event fires as a structural guarantee of "an `IpBan` row now exists," not a convention producers must follow. This is the "observer for owned model" pattern already established by `CommentObserver`, applied to guarantee event fan-out rather than to send a single mail.

This does mean the observer must derive "source" (honeypot vs. manual vs. spam-escalation) from data already on the row - `banned_method`/`banned_url`/`banned_body` naturally distinguish the honeypot path (populated) from the manual admin path (a moderator-driven ban has no HTTP method/URL/body from the offending request, since the source is a comment, not a live request). The manual admin action's `IpBan::create()` call should set a distinct marker, e.g. `banned_method = 'admin'` or an explicit new column, if the existing fields can't unambiguously encode "manually banned by a moderator" - noted as an implementation decision for tasks.md, not a schema change decided here beyond flagging it.

### `IpBanned` is a plain event class, not reuse of Canvas's `Event::listen([...], Listener::class)` array-registration style
Canvas's `PostPublished`/etc. are third-party events already shaped that way; `AppServiceProvider` just listens to them. `IpBanned` is a new first-party event, so it gets a conventional `app/Events/IpBanned.php` class (constructor-promoted `ip`, `source`, and the triggering `IpBan` model or its id) and `Event::listen(IpBanned::class, SendIpBanAlert::class)` (or the shorthand `SendIpBanAlert::class` invokable listener array-form already used for `FlushBlogFeedCache`) registered in `AppServiceProvider::boot()`, consistent with the existing registration site for event/listener wiring in this codebase.

### Manual admin ban reuses `Comment::ip_address`, no new input field
The moderation UI already has the IP address on-screen for every comment (`CommentModerationController::index()` already returns `ip_address` in the Inertia payload). The new action takes only a comment id, looks up its `ip_address`, and creates the `IpBan` row from that - no free-text IP entry field, which avoids validating arbitrary operator-supplied IP strings.

## Risks / Trade-offs

- [Source disambiguation relies on existing columns being repurposed / a new column added] → Flag explicitly as a tasks.md item to decide the concrete column/value scheme before writing the observer, so the observer's source-detection logic isn't guesswork.
- [Observer fires synchronously in the request cycle that creates the `IpBan` row (honeypot request, or the admin moderator's request)] → `SendIpBanAlert` must not perform slow synchronous work (e.g. a live webhook call) in the listener; the notification hook stays a stub per Non-Goals, and the structured log write is fast enough to run inline. If a real webhook is added later, revisit `ShouldQueue` at that time.
- [`firstOrCreate` in the honeypot path already suppresses duplicate rows/events for repeat honeypot hits from the same IP, but the manual admin path uses a comment's IP which could already be banned] → Manual ban action should also use `firstOrCreate`-equivalent semantics (or check first) so re-triggering "ban this IP" on an already-banned IP is a no-op, per the spec's duplicate-ban scenario.

## Open Questions

- Exact encoding for "source" on the `IpBan` row (reuse `banned_method` with a sentinel value like `'admin'`, or add a dedicated `source`/`banned_via` column) - deferred to tasks.md/implementation; does not change the event contract, the specs, or the task breakdown's shape, only a column name.
