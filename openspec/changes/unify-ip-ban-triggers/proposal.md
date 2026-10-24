## Why

`IpBan` rows are currently created from a single place: `WordpressController::ban()`, invoked by the WordPress honeypot routes (`/wp-login.php`, `/wp-admin`). Two more independent producers are planned — a repeated-spam escalation listener (separate change: `escalate-repeat-spam-to-ipban`) and a manual "ban this IP" action a moderator can trigger from the comment-moderation admin UI. Each of these represents the same real-world fact ("this IP is now banned") but today there is no shared place to react to that fact. Without a unifying event, alerting/logging logic would have to be duplicated at each producer, or bolted onto `WordpressController` in a way the other two producers can't reuse. An `IpBan` `Observer`-driven domain event lets every producer get the same reaction "for free," regardless of which code path created the row.

## What Changes

- Add an `IpBanned` domain event, dispatched whenever an `IpBan` row is created, from a single centralized source (an `IpBan` model observer's `created()` hook) so all current and future producers get it automatically rather than each producer dispatching it manually.
- Add a `SendIpBanAlert` listener that reacts to `IpBanned` uniformly: writes a structured security-audit log line, and stubs out a Slack/webhook notification hook for later wiring.
- Add a manual "ban this IP" action to the comment-moderation admin UI (`/admin/comments`), gated on the same `manage-blog` permission already used for spam moderation, that creates an `IpBan` row from a comment's recorded `ip_address` (reusing `Comment::visible()`'s existing `ip_address` column — no new column needed).
- Wire the existing honeypot-driven ban path (`WordpressController::ban()`) through the same `IpBan::firstOrCreate()`-triggered observer, so no honeypot code changes are required beyond continuing to create `IpBan` rows the same way.
- This proposal's event contract is producer-agnostic: it does not require `escalate-repeat-spam-to-ipban` to exist or be built first, only that any future producer create `IpBan` rows the normal Eloquent way so the observer fires.

## Capabilities

### New Capabilities
- `ip-ban-event-notifications`: the `IpBanned` domain event (dispatched via `IpBan` model observer on `created()`), the `SendIpBanAlert` listener's logging/notification-stub behavior, and the new manual admin "ban this IP" action with its `manage-blog` authorization gate.

### Modified Capabilities
(none — no existing `openspec/specs/` capability covers IP banning today; this proposal introduces the first one, scoped to the delta described above rather than documenting all pre-existing `IpBan`/`IpMiddleware` behavior.)

## Impact

- `app/Models/IpBan.php` — gains an observed `created()` lifecycle hook (registered via a new `IpBanObserver`, following the existing `Comment`/`CommentObserver` pattern).
- `app/Providers/AppServiceProvider.php` — registers `IpBan::observe(IpBanObserver::class)`.
- New: `app/Observers/IpBanObserver.php`, `app/Events/IpBanned.php`, `app/Listeners/SendIpBanAlert.php`.
- `app/Http/Controllers/Admin/CommentModerationController.php` and `routes/admin.php` — gain a new "ban IP" action/route alongside the existing spam/not-spam actions.
- `app/Http/Controllers/WordpressController.php` — unchanged in behavior; its existing `IpBan::firstOrCreate()` call now also triggers the observer/event chain.
- No changes to `app/Http/Middleware/IpMiddleware.php` (it only checks the ban list, it does not create bans, so it is out of scope for this proposal).
