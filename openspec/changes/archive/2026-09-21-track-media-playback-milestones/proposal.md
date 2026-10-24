## Why

Jellyfin webhooks arrive at `LocalMediaController` and are persisted onto a single `LocalMedia` row per item, but nothing in that flow marks the moment playback actually finishes. There is no signal a listener can react to when someone crosses "finished watching" — no history of what was recently completed, and no way to invalidate any cached "currently watching" state once the item is done. Bolting these concerns onto the webhook handler (or a `LocalMedia` model observer) would couple three unrelated consumers — a finished-history log, cache invalidation, and a future social-posting integration — into one method that has to know about all of them. This change introduces a domain event fired once per completion so each consumer can subscribe independently.

## What Changes

- Add a `MediaPlaybackMilestoneReached` event, dispatched from `LocalMediaController::handlePlayback()` when a `PlaybackProgress` (or `PlaybackStart` carrying progress) webhook reports playback position crossing an 90%-of-runtime threshold for a given `LocalMedia` item, at most once per playback session.
- Add a `RecordRecentlyFinishedMedia` listener that appends the finished item to a "recently finished" history, for potential future homepage display. This is additive persistence only — no existing display changes.
- Introduce a short-TTL cache (`Cache::remember`, keyed per "currently watching" lookup) in front of the `currentlyWatching()` endpoint and the `CurrentlyWatching` component's query, since neither is cached today and a milestone-driven invalidation would otherwise have nothing to invalidate. Add an `InvalidateCurrentlyWatchingCache` listener that forgets that cache key when a milestone fires, so a finished item cannot continue to display as "currently watching" for the remainder of the TTL.
- Add a `LogSocialPostStub` listener as an explicit stand-in for a future "post to social" integration. It only logs that it received the event (`Log::info`) — it SHALL NOT perform any outbound HTTP call or actually post anywhere.
- Register all three listeners against the new event so the webhook controller and the `LocalMedia` model remain unaware of any of them.

## Capabilities

### New Capabilities

- `media-playback-milestones`: the completion-threshold event, its dispatch condition, and the three independent listeners reacting to it.

### Modified Capabilities

(none — no existing spec capability covers `LocalMediaController` or `CurrentlyWatching` today; this is a new capability area, not a change to previously specified behavior.)

## Impact

- `app/Http/Controllers/LocalMediaController.php` — `handlePlayback()` gains a threshold check and an event dispatch; no change to its response contract or webhook payload handling.
- `app/Events/MediaPlaybackMilestoneReached.php` (new)
- `app/Listeners/RecordRecentlyFinishedMedia.php` (new)
- `app/Listeners/InvalidateCurrentlyWatchingCache.php` (new)
- `app/Listeners/LogSocialPostStub.php` (new)
- `app/Http/Controllers/LocalMediaController.php::currentlyWatching()` and `app/View/Components/CurrentlyWatching.php` — gain a cache read (new dependency: the cache store already configured for the app).
- A new table or storage location for "recently finished" history (exact shape decided in design.md).
- `app/Providers/EventServiceProvider.php` or equivalent listener registration (Laravel 13 uses auto-discovery or `AppServiceProvider::boot()`'s `Event::listen`) — wires the event to its three listeners.
