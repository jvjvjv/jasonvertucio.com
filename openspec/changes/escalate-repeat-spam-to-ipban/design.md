## Context

`CommentModerationController::markSpam()` (app/Http/Controllers/Admin/CommentModerationController.php) currently only calls `$comment->update(['is_spam' => true, 'approved_at' => null])`. `Comment` (app/Models/Comment.php) has `ip_address`/`user_agent` columns but nothing reads `ip_address` for abuse purposes today except `IpMiddleware` reading the separate `ip_ban` table.

`IpBan` (app/Models/IpBan.php) maps to the `ip_ban` table (migration `2020_04_30_123908_create_ip_ban_table.php`), which has **no `id` primary key** and three non-nullable, no-default columns: `banned_method`, `banned_url`, `banned_body` (plus `ip`, timestamps). Those columns describe an HTTP request the existing WordPress-honeypot ban path (`IpMiddleware` callers, not present in this codebase's tracked-down producer but implied by the schema) was built around. This escalation path has no HTTP request to describe — it is triggered by a moderator clicking a button — so it cannot populate those columns the same way.

`IpMiddleware` (app/Http/Middleware/IpMiddleware.php) caches the full banned-IP list under `banned_ip_list` for 300 seconds. A ban created by this feature does not take effect until that cache expires or is cleared.

The queue connection is `database`, and `EscalateRepeatSpamToIpBan` will be the second thing on the `default` queue alongside `CommentReceivedMail` (see CLAUDE.md § Queues). `queue:work` in production must be restarted after deploy per existing operational documentation — this change does not alter that requirement, just adds another consumer.

## Goals / Non-Goals

**Goals:**
- Add `CommentMarkedAsSpam` as a Laravel event with a design that supports more than one producer without modification.
- Wire exactly one producer now (`markSpam()`), and document the observer heuristic as a future second producer.
- Implement `EscalateRepeatSpamToIpBan` as a queued listener performing the count/threshold/ban logic.
- Resolve the `ip_ban` schema mismatch (non-nullable HTTP-shaped columns) so an escalation-created ban is a valid row.

**Non-Goals:**
- Building the automated spam-detection heuristic itself (only documented as a future producer).
- Changing `IpMiddleware`'s caching behavior or ban-check logic.
- Adding an unban/appeal workflow.
- Adding admin UI to view or configure escalation (config-file only, per proposal).

## Decisions

**Event shape.** `App\Events\CommentMarkedAsSpam` is a plain event class (not `ShouldBroadcast`) carrying `public readonly Comment $comment`. Passing the model (not just an `ip_address` string) lets the listener, and any future listener, reach `post`, `user`, `email`, etc. if escalation logic grows. Alternative considered: pass only the IP address — rejected because it would force a second event or a lookup if any future consumer needs more comment context.

**Dispatch point.** Dispatched in `markSpam()` immediately after `$comment->update(...)` succeeds, via `CommentMarkedAsSpam::dispatch($comment)`. Not dispatched in `markNotSpam()` — no spam determination is being made there, so no escalation signal applies. Not dispatched from a model observer/event on `Comment` itself, because the *manual moderation action* is the producer being wired in this change; a `Comment` "updated" event would fire for both markSpam and markNotSpam and for unrelated field changes, muddying "was this a spam determination."

**Future second producer (documented, not built).** CLAUDE.md-adjacent context and the proposal call out that an automated heuristic in `App\Observers\CommentObserver::created()` (app/Observers/CommentObserver.php) — or an `updated()` hook — could dispatch the same `CommentMarkedAsSpam` event when it programmatically sets `is_spam = true` on comment creation/edit. Because the event only depends on `Comment` and the listener only depends on the event, wiring that second producer later is: set `is_spam` as usual, then dispatch `CommentMarkedAsSpam::dispatch($comment)` from the observer. No change to the event class or the listener is anticipated. This is explicitly **not** implemented in this change.

**Listener: queued, idempotent, defensive.** `App\Listeners\EscalateRepeatSpamToIpBan implements ShouldQueue`. Logic:
1. Resolve `ip_address` from the event's comment; if null/blank, return without action (some comments may lack a recorded IP — historical data or edge cases).
2. If `IpBan::where('ip', $ip)->exists()`, return (idempotency requirement).
3. Count `Comment::where('ip_address', $ip)->where('is_spam', true)->where('created_at', '>=', now()->sub($window))->count()`.
4. If count `>= threshold`, create the `IpBan` row.

Wrapped so that a thrown exception surfaces as a failed queue job (retried per the worker's `--tries=3`, then logged to `failed_jobs`) rather than propagating to the HTTP request — this is inherent to using a queued listener and satisfies the "escalation failure does not affect the moderation action" requirement without extra try/catch in the controller.

**Config keys.** New keys in `config/comments.php`, following the existing `env()`-in-config-only convention used by `notification_email`/`rate_limit_per_minute`:
```php
'spam_escalation' => [
    'enabled' => (bool) env('COMMENT_SPAM_ESCALATION_ENABLED', true),
    'threshold' => (int) env('COMMENT_SPAM_ESCALATION_THRESHOLD', 3),
    'window_hours' => (int) env('COMMENT_SPAM_ESCALATION_WINDOW_HOURS', 24),
],
```
An `enabled` toggle is included (not required by the proposal's prose but implied by "config-driven" and useful for disabling escalation without a deploy) — the listener's `handle()` returns immediately when `false`.

**`ip_ban` schema fix.** A new migration adds nullable-with-default columns rather than reworking the existing ones (avoids touching `IpMiddleware`'s existing read path or the honeypot producer's existing write path):
- Add `id` as the primary key (`$table->id()`, placed first) — the table currently has none, which makes individual rows impossible to reference (e.g., for tests or future admin tooling) and is worth fixing while touching this table.
- Make `banned_method`, `banned_url`, `banned_body` nullable.
- `EscalateRepeatSpamToIpBan` creates its row with `banned_method`/`banned_url` set to a fixed marker (e.g. `'ESCALATION'`) and `banned_body` set to a short human-readable reason (e.g. `"Repeat spam: {count} spam comments in {window}"`), rather than leaving them null, so the existing admin-facing ban list (if any renders these columns) still shows something meaningful rather than blank cells. Using nullable+populated (vs. leaving them empty) was chosen over adding a new `source`/`reason` column pair, to avoid widening the table's shape for a single new producer — revisit if a third ban producer needs structured metadata.

**Cache staleness is accepted, not fixed.** `IpMiddleware`'s 300-second `banned_ip_list` cache means an escalated ban can take up to 5 minutes to start blocking requests. This is existing, pre-dating behavior for every ban producer, not something this change needs to fix; noted here so it isn't mistaken for a bug during review.

## Risks / Trade-offs

- **[Risk]** A shared IP (NAT, university, corporate proxy, shared hosting) could trip the threshold from unrelated visitors and ban legitimate traffic. → Mitigation: threshold defaults to 3 within 24 hours (not 1-shot), configurable per-deployment; no automatic unban is in scope, but the ban row is a normal `IpBan` a human can delete manually.
- **[Risk]** Queue worker must be restarted after this deploy (per CLAUDE.md § Queues) or the listener will not run despite code being present. → Mitigation: call out explicitly in tasks.md; this is an existing operational requirement, not new risk, but easy to forget.
- **[Trade-off]** Populating `banned_method`/`banned_url`/`banned_body` with marker/reason text instead of leaving them null (or removing them) keeps the migration additive and low-risk, at the cost of those columns now meaning two different things depending on ban source. Acceptable given the alternative (restructuring `ip_ban` into a polymorphic/source-typed table) is out of proportion to this change.
- **[Risk]** Counting `Comment::where('ip_address', $ip)` at listener-run time is a live count, not a snapshot at the moment threshold was crossed — if spam comments are deleted/restored between events, results could vary. → Accepted: `Comment` uses `SoftDeletes`, and moderation does not delete comments (only marks spam), so this is a theoretical, not practical, concern for this workflow.

## Migration Plan

1. Ship the `ip_ban` migration (add `id`, make three columns nullable) — additive, no data loss, safe to deploy independently.
2. Ship `CommentMarkedAsSpam`, `EscalateRepeatSpamToIpBan`, the `markSpam()` dispatch, and the new config keys together.
3. Restart the `queue:work` supervisor program after deploy (see CLAUDE.md § Queues) so the listener is picked up.
4. No backfill: existing spam comments predating this change are not retroactively counted against the threshold at deploy time; escalation only evaluates on the next `CommentMarkedAsSpam` dispatch, though the count query itself does include old rows within the window once triggered.

Rollback: revert the controller/event/listener/config changes; the `ip_ban` migration's `down()` is safe to run if desired but not required to roll back the feature (nullable columns and an added `id` do not break `IpMiddleware`'s existing read).
