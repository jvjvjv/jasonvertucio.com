## Why

`CommentModerationController::markSpam()` only flips `is_spam`/`approved_at` on the comment being moderated — nothing downstream reacts, so a visitor who keeps posting spam from the same IP has to be caught and marked spam by hand every single time. CLAUDE.md already documents the intent that a spam determination should feed `IpBan`, but no producer or consumer of that signal exists yet. This proposal wires manual moderation to an escalation path, via an event designed to also accommodate a future automated spam-detection producer without redesign.

## What Changes

- `markSpam()` dispatches a new `CommentMarkedAsSpam` domain event after persisting the spam state, instead of only updating the two columns.
- A new queued listener, `EscalateRepeatSpamToIpBan`, counts spam comments (`is_spam = true`) from the same `ip_address` within a rolling, config-driven window and creates an `IpBan` row when a config-driven threshold is exceeded.
- The threshold, window, and escalation toggle are read from configuration (new `config/comments.php` keys), never hardcoded or read via `env()` outside config.
- Escalation is idempotent: an `ip_address` that already has an `IpBan` row is not banned again.
- The event/listener pair is documented as designed for multiple producers: `markSpam()` is the only producer wired in this change, but a not-yet-built automated heuristic in `App\Observers\CommentObserver` is a second, independent future producer of the same event, requiring no change to the event or listener.

## Capabilities

### New Capabilities
- `comment-spam-ipban-escalation`: repeat-spam detection from the same IP and automatic `IpBan` creation, triggered by `CommentMarkedAsSpam`, with configurable threshold/window and idempotent (no double-ban) behavior.

### Modified Capabilities
- `blog-comment-moderation`: marking a comment as spam now has an additional observable effect — a `CommentMarkedAsSpam` event is dispatched, which existing behavior (the column changes, the row retention) is unaffected by.

## Impact

- `app/Http/Controllers/Admin/CommentModerationController.php` (`markSpam()`) — dispatches the new event.
- New: `app/Events/CommentMarkedAsSpam.php`.
- New: `app/Listeners/EscalateRepeatSpamToIpBan.php`, queued (`ShouldQueue`), the second consumer of the `default` queue alongside `CommentReceivedMail`.
- `app/Models/IpBan.php` / `ip_ban` table — the table currently has no `id` primary key and `banned_method`/`banned_url`/`banned_body` are non-nullable columns with no defaults; an escalation-created row does not have a "method/url/body" in the HTTP-abuse sense, which the design and migration must account for.
- `config/comments.php` — new keys for escalation threshold, window, and enabled toggle.
- `IpMiddleware` — no code change, but its 5-minute `banned_ip_list` cache means a newly escalated ban does not take effect instantly; this is a documented latency, not a defect, and is called out in the design.
