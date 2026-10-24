## 1. `ip_ban` schema fix

- [ ] 1.1 Add a migration that adds `id` as the primary key (first column, via `$table->id()`) to the `ip_ban` table and verify `php artisan migrate` runs cleanly against both the `jasonvertucio` and `wink` databases (per project convention, run `DB_DATABASE=wink php artisan migrate` too)
- [ ] 1.2 In the same or a companion migration, make `banned_method`, `banned_url`, `banned_body` on `ip_ban` nullable, and verify via `php artisan tinker --execute "print_r(Schema::getColumnListing('ip_ban'));"` plus inspecting the column definitions (e.g. `DESCRIBE ip_ban`) show them nullable
- [ ] 1.3 Verify `IpMiddleware`'s existing read path (`IpBan::all()->map(...)`) still works unchanged by exercising the banned-IP check in a feature test or manually

## 2. Event

- [ ] 2.1 Create `App\Events\CommentMarkedAsSpam` (`php artisan make:event CommentMarkedAsSpam`) carrying `public readonly Comment $comment`, and verify the class exists at `app/Events/CommentMarkedAsSpam.php`
- [ ] 2.2 Update `CommentModerationController::markSpam()` (app/Http/Controllers/Admin/CommentModerationController.php, ~line 50) to dispatch `CommentMarkedAsSpam::dispatch($comment)` after the `$comment->update(...)` call, and verify `markNotSpam()` is left unchanged (no dispatch)
- [ ] 2.3 Add/update a feature test asserting `markSpam()` dispatches `CommentMarkedAsSpam` (`Event::fake()` + `Event::assertDispatched`) and that `markNotSpam()` does not, and verify it passes: `php artisan test --compact --filter=markSpam`

## 3. Config

- [ ] 3.1 Add a `spam_escalation` array to `config/comments.php` with `enabled`, `threshold`, `window_hours` keys, each backed by an `env()` call with the defaults from design.md (`true`, `3`, `24`), and verify `php artisan config:show comments.spam_escalation` reflects the defaults with no `.env` overrides set

## 4. Listener

- [ ] 4.1 Create `App\Listeners\EscalateRepeatSpamToIpBan implements ShouldQueue` (`php artisan make:listener EscalateRepeatSpamToIpBan --event=CommentMarkedAsSpam`) and verify the class exists at `app/Listeners/EscalateRepeatSpamToIpBan.php`
- [ ] 4.2 Implement `handle()`: return early if `spam_escalation.enabled` is false or the comment's `ip_address` is blank; return early if `IpBan::where('ip', $ip)->exists()`; count `Comment::where('ip_address', $ip)->where('is_spam', true)->where('created_at', '>=', now()->subHours($window))->count()`; create an `IpBan` (with `ip`, and `banned_method`/`banned_url`/`banned_body` populated per design.md's marker/reason convention) when the count meets or exceeds the configured threshold
- [ ] 4.3 Register the event-to-listener mapping (Laravel 13 auto-discovers listeners with `#[AsEventListener]` or via `Event::listen` — follow whatever convention `CommentObserver`'s registration in `AppServiceProvider`/`EventServiceProvider` uses, if one exists, otherwise add an explicit listener attribute) and verify `php artisan event:list` shows `CommentMarkedAsSpam` mapped to `EscalateRepeatSpamToIpBan`
- [ ] 4.4 Add a feature test covering: (a) threshold reached creates an `IpBan`, (b) below-threshold does not, (c) spam comments outside the window do not count, (d) an IP that already has an `IpBan` is not banned again, (e) `enabled = false` short-circuits with no ban created; verify with `php artisan test --compact --filter=EscalateRepeatSpamToIpBan`

## 5. Documentation

- [ ] 5.1 Update CLAUDE.md's Comment System section to describe the new `CommentMarkedAsSpam` event, the `EscalateRepeatSpamToIpBan` listener, and the new `config/comments.php` keys, matching the level of detail already given to the AI-persona resume editing section
- [ ] 5.2 Add a note to CLAUDE.md's Queues section (or cross-reference it) that `EscalateRepeatSpamToIpBan` is a second consumer on the `default` queue and requires the same `supervisorctl restart` after deploy as `CommentReceivedMail`

## 6. Verification

- [ ] 6.1 Run the full test suite (`php artisan test --compact`) and confirm no regressions
- [ ] 6.2 Manually verify end-to-end in a local environment: mark enough comments from one test IP as spam via `/admin/comments`, run the queue worker, and confirm an `IpBan` row appears for that IP with the expected `banned_method`/`banned_url`/`banned_body` marker values
