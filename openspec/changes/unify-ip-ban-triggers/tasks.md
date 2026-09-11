## 1. Source disambiguation on `IpBan`

- [ ] 1.1 Decide and implement the concrete "source" encoding on `ip_ban` rows (e.g. reuse `banned_method` with a sentinel value such as `'admin'` for manual bans, vs. adding a dedicated `source`/`banned_via` column via migration) and verify the honeypot path (`banned_method` = the real HTTP method) and the manual admin path are unambiguously distinguishable by reading a row back.

## 2. Event and observer

- [ ] 2.1 Create `app/Events/IpBanned.php` carrying the banned IP, the derived source, and the triggering `IpBan` model (or its id), and verify it is constructible in a unit test.
- [ ] 2.2 Create `app/Observers/IpBanObserver.php` with a `created(IpBan $ipBan)` hook that dispatches `IpBanned`, and register it via `IpBan::observe(IpBanObserver::class)` in `AppServiceProvider::boot()` (alongside the existing `Comment::observe(CommentObserver::class)` line), and verify with a feature test using `Event::fake()` that creating an `IpBan` row dispatches `IpBanned` exactly once.
- [ ] 2.3 Verify via test that `IpBan::firstOrCreate()` on an already-existing IP does not dispatch a second `IpBanned` event.

## 3. Alert listener

- [ ] 3.1 Create `app/Listeners/SendIpBanAlert.php` that writes a structured security-audit log entry (banned IP, source, timestamp) when handling `IpBanned`, and stub the Slack/webhook notification hook as a no-op/TODO without a live integration.
- [ ] 3.2 Register the listener in `AppServiceProvider::boot()` via `Event::listen(IpBanned::class, SendIpBanAlert::class)`, consistent with the existing `Event::listen([...], FlushBlogFeedCache::class)` registration, and verify with a test that dispatching `IpBanned` produces the expected log entry (e.g. via `Log::spy()`/`Log::shouldReceive()` or a test log channel assertion).
- [ ] 3.3 Verify the listener does not perform any blocking synchronous external call (per design.md's Risk on request-cycle latency) — code review / grep confirms no live HTTP/webhook call exists yet, only the stub.

## 4. Manual admin "ban this IP" action

- [ ] 4.1 Add a `banIp(Comment $comment)` action to `Admin/CommentModerationController` that looks up the comment's `ip_address`, creates (or reuses, via `firstOrCreate`-equivalent) an `IpBan` row with the source from task 1.1, and returns an error (no row created) when the comment has no recorded `ip_address`.
- [ ] 4.2 Add the route in `routes/admin.php` under the existing `manage-blog`-gated group (alongside `comments.spam`/`comments.not-spam`), e.g. `POST /admin/comments/{comment}/ban-ip`, and verify with a feature test that a `manage-blog` user can hit it and an IP ban row is created.
- [ ] 4.3 Verify with a feature test that a user without `manage-blog` is refused (403/redirect, matching the existing spam/not-spam action's authorization behavior) and no `IpBan` row is created.
- [ ] 4.4 Verify with a feature test that triggering the action on an already-banned IP does not create a duplicate row or re-fire `IpBanned` (per spec's duplicate-ban scenario).
- [ ] 4.5 Add the "ban this IP" control to the comment-moderation admin UI (`resources/js/admin/pages/comments/Index.tsx`) alongside the existing spam/not-spam controls, following the existing action-button pattern in that file.

## 5. Cross-cutting verification

- [ ] 5.1 Run the full test suite for the modified areas (`php artisan test --compact --filter=IpBan`, plus the comment moderation feature tests) and confirm all pass.
- [ ] 5.2 Manually verify (or via feature test) that the pre-existing honeypot flow (`POST /wp-login.php`) still creates an `IpBan` row exactly as before and now also triggers `IpBanned`/`SendIpBanAlert`, with no change to `IpMiddleware`'s ban-checking behavior.
