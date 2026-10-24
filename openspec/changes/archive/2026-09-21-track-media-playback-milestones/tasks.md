## 1. Schema

- [x] 1.1 Migration: add nullable `milestone_reached_at` timestamp column to `local_media`, and verify `php artisan migrate` runs cleanly on both the app (`jasonvertucio`) and test (`wink`) databases per CLAUDE.md.
- [x] 1.2 Migration: create `media_playback_milestones` table (`id`, `local_media_id` FK to `local_media`, `title`, `media_type`, `finished_at`, timestamps) and verify it runs cleanly on both databases.
- [x] 1.3 Add a `MediaPlaybackMilestone` Eloquent model (fillable: `local_media_id`, `title`, `media_type`, `finished_at`; cast `finished_at` to `datetime`) with a `localMedia()` `belongsTo` relation, and verify `php artisan tinker --execute` can create and fetch a row.

## 2. Event

- [x] 2.1 Create `app/Events/MediaPlaybackMilestoneReached.php` with readonly `media` (`LocalMedia`), `playbackPositionTicks` (int), `playbackDurationTicks` (int), `reachedAt` (`CarbonImmutable`) properties, and verify it instantiates via a quick unit test asserting the properties are exposed as constructed.

## 3. Milestone detection and dispatch

- [x] 3.1 In `LocalMediaController::handlePlayback()`, after the existing `updateOrCreate`/`PlaybackStart` increment block, compute whether `playback_position / playback_duration >= 0.90` (guarding against a zero/missing duration) and `milestone_reached_at` is still null on the row.
- [x] 3.2 When the threshold is newly crossed, set `milestone_reached_at = now()` on the `LocalMedia` row and dispatch `MediaPlaybackMilestoneReached` with the media instance and the webhook's raw position/duration ticks.
- [x] 3.3 When a `PlaybackStart` webhook begins a new session for an item, reset `milestone_reached_at` to null so a later re-watch can raise the milestone again.
- [x] 3.4 Add a Feature test (`tests/Feature/LocalMediaMilestoneTest.php`) covering: crossing the threshold dispatches the event exactly once (`Event::fake()` + `assertDispatchedTimes`); a second `PlaybackProgress` webhook past the threshold in the same session does not dispatch again; a webhook with no/zero `RunTimeTicks` never dispatches; a `PlaybackStart` after a completed session resets the flag and allows a new milestone. Run via `php artisan test --compact --filter=LocalMediaMilestoneTest`.

## 4. Recently-finished history listener

- [x] 4.1 Create `app/Listeners/RecordRecentlyFinishedMedia.php` handling `MediaPlaybackMilestoneReached` by inserting a `MediaPlaybackMilestone` row (title, media type, `finished_at` from the event), wrapped in try/catch logging via `Log::error` on failure so it never throws.
- [x] 4.2 Feature test asserting a dispatched event results in a `media_playback_milestones` row with the expected fields; run `php artisan test --compact --filter=RecordRecentlyFinishedMedia`.

## 5. Currently-watching cache and invalidation listener

- [x] 5.1 Add a shared accessor for the "currently watching" lookup (e.g. a static method on `LocalMedia` or a small query class) wrapping the existing query in `Cache::remember('currently-watching', now()->addSeconds(30), ...)`, and update both `LocalMediaController::currentlyWatching()` and `CurrentlyWatching::__construct()` to use it instead of querying directly.
- [x] 5.2 Create `app/Listeners/InvalidateCurrentlyWatchingCache.php` handling `MediaPlaybackMilestoneReached` by calling `Cache::forget('currently-watching')`, wrapped in try/catch logging via `Log::error` on failure.
- [x] 5.3 Feature test: populate the cache via a `currentlyWatching()` request, dispatch the milestone event, assert `Cache::has('currently-watching')` is false afterward (or that a subsequent request reflects fresh data); run `php artisan test --compact --filter=InvalidateCurrentlyWatchingCache`.

## 6. Social-post stub listener

- [x] 6.1 Create `app/Listeners/LogSocialPostStub.php` handling `MediaPlaybackMilestoneReached` by writing a single `Log::info` line (e.g. "social post stub: would post {title}") and performing no outbound HTTP call, wrapped in try/catch logging via `Log::error` on failure.
- [x] 6.2 Unit/feature test asserting the listener logs and makes no HTTP calls (e.g. `Http::fake()` + `Http::assertNothingSent()`); run `php artisan test --compact --filter=LogSocialPostStub`.

## 7. Wiring and isolation

- [x] 7.1 Register all three listeners against `MediaPlaybackMilestoneReached` in `AppServiceProvider::boot()` via `Event::listen(MediaPlaybackMilestoneReached::class, [...])`, following the existing `FlushBlogFeedCache` registration pattern.
- [x] 7.2 Feature test asserting one listener throwing (mock/force a failure in one, e.g. via a bound fake that throws) does not prevent the webhook response from succeeding and does not prevent the other two listeners' effects from occurring; run `php artisan test --compact --filter=MilestoneListenerIsolation`.

## 8. Full verification

- [x] 8.1 Run the full suite (`php artisan test --compact`) and confirm no regressions, per PHPUnit rules in CLAUDE.md/Boost guidelines.
