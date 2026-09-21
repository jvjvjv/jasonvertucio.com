## Context

`LocalMediaController::handlePlayback()` is the single place `PlaybackStart`/`PlaybackProgress` webhooks land; it `updateOrCreate`s one `LocalMedia` row per `jellyfin_item_id` and increments `play_count` only on `PlaybackStart`. There is no session concept beyond that row, no "finished" state, and today no cache — `currentlyWatching()` and `CurrentlyWatching::__construct()` both query `LocalMedia` directly on every call. `AppServiceProvider::boot()` already wires one cross-cutting event today: `Event::listen([PostPublished::class, PostUpdated::class, ...], FlushBlogFeedCache::class)` for Canvas post events. That's the precedent this change follows, in the other direction — one event, several listeners, instead of several events into one listener. See proposal.md - Why.

## Goals / Non-Goals

**Goals:**
- Fire one event, from one place, when a playback session crosses the completion threshold.
- Let each of the three consumers (history, cache invalidation, social stub) be added, removed, or fail without touching the webhook controller, `LocalMedia`, or each other.
- Make a listener failure visible (logged) without aborting the webhook response or the other listeners.

**Non-Goals:**
- Building an actual social-posting integration — `LogSocialPostStub` only logs.
- Building UI to surface the recently-finished history — this change only persists it.
- General-purpose caching of all `LocalMedia` queries — only the "currently watching" read path gets a cache, and only because the milestone event needs something concrete to invalidate.
- Modeling Jellyfin "playback sessions" as a first-class concept beyond what's needed to dedupe the milestone once per session.

## Decisions

### Event shape
`MediaPlaybackMilestoneReached` is a plain event class (not `ShouldBroadcast`) carrying:
- `public readonly LocalMedia $media` — the updated row (has `jellyfin_item_id`, `jellyfin_user_id`, `title`, `media_type`, etc. already).
- `public readonly int $playbackPositionTicks` and `public readonly int $playbackDurationTicks` — the values from the triggering webhook, since `$media->playback_position` may already have been overwritten by the time a queued listener runs.
- `public readonly \Carbon\CarbonImmutable $reachedAt` — capture time, not `now()` re-evaluated per listener.

No `User` model reference: Jellyfin's `UserId` is an opaque string already stored as `jellyfin_user_id` on `LocalMedia`; there is no corresponding app `User`.

### Dispatch point and dedupe
Dispatched from `LocalMediaController::handlePlayback()`, after the `updateOrCreate` and after the existing `PlaybackStart` play-count increment, guarded by:
1. `$data['NotificationType']` is `PlaybackStart` or `PlaybackProgress` (i.e., inside the existing `case` branch — no new webhook type is introduced).
2. Runtime is known (`playback_duration > 0`) and `playback_position / playback_duration >= 0.90`.
3. The milestone has not already been raised for this session.

Session-level dedupe needs a marker that survives across the multiple `PlaybackProgress` webhooks Jellyfin sends for one playback. Add a nullable `milestone_reached_at` timestamp column to `local_media` (reset to null whenever a new `PlaybackStart` begins, so a later re-watch can raise the milestone again). The check-and-set happens inside `handlePlayback()`: if the threshold is met and `milestone_reached_at` is still null, set it and dispatch; otherwise skip. This keeps dedupe colocated with the one place progress is recorded, rather than pushing session-tracking logic into the event or a listener.

Alternative considered: derive "already reached" from `playback_position` alone (e.g., only fire if the *previous* stored position was below threshold and the *new* one is at/above it). Rejected — it's equivalent but fragile against out-of-order or retried webhooks, whereas an explicit flag is unambiguous and cheap.

### Why an event instead of the controller or a `LocalMedia` observer
This is the inverse of the existing `FlushBlogFeedCache` case: there, four different Canvas *events* converge on one listener. Here, one condition inside one method needs to fan out to three *unrelated* actions. Putting all three inline in `handlePlayback()` (or in a model `saved()` observer, which fires on every save regardless of whether this was the save that crossed the threshold) would force:
- the webhook handler to know about a "recently finished" history table it has nothing to do with,
- the webhook handler to know about a cache key that belongs to a display component,
- a stub for a not-yet-built social integration to live inside webhook-ingestion code, guaranteeing it gets touched again when that integration is actually built.

An event lets each concern own its own listener class, tested and reasoned about independently, registered in one line each in `AppServiceProvider::boot()`, with no consumer aware the others exist.

### Listener isolation
Each of the three listeners implements `ShouldQueue`? No — keep them synchronous (matching `FlushBlogFeedCache`, which is also synchronous) since none does meaningfully slow I/O (a DB insert, a `Cache::forget`, a log line), and the app's only existing queued work is mail (see CLAUDE.md). To satisfy "one listener's failure must not block the others or the webhook response," wrap the `Event::dispatch()` call itself — not each listener — is insufficient, since Laravel's default synchronous dispatcher stops at the first exception. Instead, each listener catches its own exceptions internally and logs them via `Log::error`, guaranteeing it never throws out to the dispatcher. This is simpler than a custom fault-tolerant dispatcher and keeps each listener's error handling visible in its own class.

### Cache for "currently watching"
Add `Cache::remember('currently-watching', now()->addSeconds(30), fn () => LocalMedia::whereNotNull('last_playback_at')->orderBy('last_playback_at', 'desc')->first())`, used by both `LocalMediaController::currentlyWatching()` and `CurrentlyWatching::__construct()` (extract to a small shared accessor, e.g. a method on `LocalMedia` or a tiny query service, to avoid duplicating the cache key/TTL in two places). `InvalidateCurrentlyWatchingCache` calls `Cache::forget('currently-watching')`. The 30s TTL is a deliberate short window — long enough to matter under load, short enough that even a missed invalidation self-heals quickly.

### Recently-finished history storage
A new `media_playback_milestones` table (id, `local_media_id` FK, `title`, `media_type`, `finished_at`, timestamps) rather than reusing `local_media` rows, since `local_media` is one-row-per-item and gets overwritten on rewatch — a history needs one row per completion. `RecordRecentlyFinishedMedia` just inserts. No model relationships beyond the FK are needed yet since nothing reads this table back in this change.

## Risks / Trade-offs

- [Listener exceptions are swallowed by design, so a broken listener fails silently] → Mitigation: each listener logs via `Log::error` on catch, so failures are visible in logs even though they don't propagate.
- [Session dedupe via a nullable column adds a migration and a reset-on-`PlaybackStart` rule that must not be forgotten] → Mitigation: the reset lives in the same `handlePlayback()` branch as the rest of session state, next to the existing `play_count` increment on `PlaybackStart`.
- [Introducing a cache changes `currentlyWatching()` from always-fresh to up-to-30s-stale] → Mitigation: 30s TTL plus milestone-driven invalidation means staleness is bounded and self-correcting; this is a deliberate, scoped trade-off, not a general caching policy change.
- [Threshold and TTL are fixed constants rather than config] → Accepted for this change; promoting them to `config/media.php` is a small follow-up if they need tuning without a deploy.

## Open Questions

- Should `milestone_reached_at` also reset on `PlaybackStop` (in case Jellyfin doesn't send a fresh `PlaybackStart` before a re-watch)? Doesn't change the specs or task breakdown — can be decided during implementation by checking actual Jellyfin webhook sequencing.
