## 1. Fix the existing defects first

These are independent of the new feature and make the rest testable.

- [x] 1.1 In `app/Models/Comment.php`, correct the `$fillable` entry `fb_parent_comment_id` to the actual column name `fb_comment_parent_id`.
- [x] 1.2 In `database/factories/CommentFactory.php`, correct the same key so factory inserts stop erroring on a non-existent column. **Also fixed:** `getOrCreatePost()` called `Post::create()` with no `id`, but Canvas's `Post` sets `$incrementing = false` and generates no UUID in `boot()` — and the `wink` test DB has zero `canvas_posts`, so that branch always ran and always failed.
- [x] 1.3 In `resources/views/mail/new-comment.blade.php`, guard `$comment->post->title` against a null relation and give the address link a `mailto:` scheme.
- [x] 1.4 Add a feature test that creates a comment via the factory and asserts the row persists — this currently fails, and passing it proves 1.1 and 1.2.

## 2. Schema

- [x] 2.1 New migration: add `approved_at` (timestamp, nullable), `is_spam` (boolean, default false), `depth` (unsigned tinyint, default 0), `ip_address` (nullable), `user_agent` (nullable) to `comments`.
- [x] 2.2 New migration: drop the `fb_user_id → users.id` foreign key added by `2025_08_30_165813_add_user_information_to_comments.php` and retype `fb_user_id` to a plain nullable string — a Facebook user id is not a UUID and the constraint rejects every row it exists to hold.
- [x] 2.3 New migration: replace the `parent_id` `onDelete('set null')` constraint from `2021_10_31_170514_create_comments_table.php`. Under `set null`, a hard delete silently promotes every reply to root level and leaves `depth` stale.
- [x] 2.4 Add an index supporting the display query (`post_id`, `approved_at`, `is_spam`).
- [x] 2.5 Run migrations against BOTH `jasonvertucio` and `wink` (`DB_DATABASE=wink php artisan migrate`) — the test suite points at `wink`.
- [x] 2.6 Update `Comment::$fillable` and `casts()` for the new columns (`approved_at` datetime, `is_spam` boolean, `depth` integer).
- [x] 2.7 Add `parent()` and `replies()` relationships to `Comment` with return type hints. Neither exists today, so nesting is currently an unusable FK.

## 3. Submission

- [x] 3.1 `php artisan make:controller CommentController` with a `store` action; register the route in `routes/blog.php` alongside the post routes.
- [x] 3.2 `php artisan make:request StoreCommentRequest` — validate `name`, `email`, `message`, optional `parent_id`, and the honeypot field. Check sibling Form Requests for whether this project uses array or string rule syntax.
- [x] 3.3 Enforce the depth cap in the request: reject when the resolved parent's `depth` is already 5.
- [x] 3.4 Reject a `parent_id` whose `post_id` differs from the target post, and reject submissions against unpublished posts.
- [x] 3.5 Compute `depth` on insert as `parent.depth + 1`, or 0 with no parent.
- [x] 3.6 Set `approved_at = now()` and `is_spam = false` on creation.
- [x] 3.7 Snapshot `name` for authenticated commenters rather than resolving it from `users` at render time.
- [x] 3.8 Record `ip_address` and `user_agent`, resolving the client address through the same Cloudflare-aware logic `IpMiddleware` uses.
- [x] 3.9 Apply rate limiting to the store route, returning 429 past the threshold.
- [x] 3.10 On honeypot hit, discard silently and return a response indistinguishable from success.
- [x] 3.11 Feature tests: anonymous submission, authenticated submission, depth-4 reply accepted, depth-5 reply rejected, cross-post parent rejected, unpublished post rejected, honeypot discarded, rate limit triggers.

## 4. Display

- [x] 4.1 Remove the `fb-comments` block from `resources/views/blog/single.blade.php` (currently lines 30–40).
- [x] 4.2 Remove the `fb:app_id` meta section from the same file (lines 57–60), eliminating its `env()` call.
- [x] 4.3 Remove `#fb-root` and the `connect.facebook.net/sdk.js` script from `resources/views/layout.blade.php` (lines 85–90).
- [x] 4.4 Delete `Route::any('/mlopnadjs22tn', ...)` from `routes/web.php:26` and delete `app/Http/Controllers/FacebookCallbackController.php`.
- [x] 4.5 Add a `<x-comment-thread />` Blade component that loads the post's comments in one query filtered by `whereNotNull('approved_at')->where('is_spam', false)`, and assembles the tree in PHP. Register the class in `app/View/Components/` following the existing component convention.
- [x] 4.6 Add a `<x-comment />` component rendering a single node: author name, body, timestamp, and a reply control hidden at `depth` 5.
- [x] 4.7 Render a tombstone for a comment excluded by the spam predicate that still has visible descendants, exposing neither body nor author. Render nothing for an excluded leaf.
- [x] 4.8 Cap visual indentation at 2 levels; for `depth > 2`, render an "in reply to {parent author}" backlink.
- [x] 4.9 Add the comment form to the post page, with the honeypot field and a reply affordance that sets `parent_id`.
- [x] 4.10 Add an empty state for a post with no comments.
- [x] 4.11 Feature tests: approved comments render, soft-deleted absent, tombstone preserves descendants at their depths, leaf spam renders nothing, depth-4 indentation matches depth-2, depth-5 offers no reply control, thread costs one query.

## 5. Notifications

- [x] 5.1 Create `config/comments.php` with `notification_email` reading `env('COMMENT_NOTIFICATION_EMAIL', 'me@jasonvertucio.com')`, following the `config/resume.php` precedent.
- [x] 5.2 Add `COMMENT_NOTIFICATION_EMAIL` to `.env.example`.
- [x] 5.3 Make `CommentReceivedMail` implement `ShouldQueue`. Note this alone changes nothing while the connection is `sync` — 5.4 is what makes it real.
- [x] 5.4 Change `QUEUE_CONNECTION` from `sync` to `database` in `.env` and `.env.example`. The `jobs` and `failed_jobs` tables already exist; `docker/supervisord.prod.conf` and `docker/supervisord.dev.conf` already run `queue:listen`.
- [x] 5.5 In `CommentObserver::created()`, read the recipient from config and suppress dispatch when the comment's author is the site owner.
- [x] 5.6 Feature tests: notification queued not sent inline (`Mail::fake()` + `Queue::fake()`), configured recipient used, default applies when unset, owner's own comment dispatches nothing, template renders with a null `post`.

## 6. Moderation

- [x] 6.1 `php artisan make:controller Admin/CommentModerationController` with `index`, `markSpam`, and `markNotSpam`.
- [x] 6.2 Register the routes in `routes/admin.php` inside the existing `['auth', 'can:...', HandleInertiaRequests::class]` group. Do **not** place them under `/canvas` — Canvas's `Route::get('/{view?}')->where('view', '(.*)')` registers at router index 38, ahead of `routes/web.php` at index 67, and swallows the whole prefix.
- [x] 6.3 Decide the permission gate: reuse an existing Keystone permission or add a `moderate-comments` one following the `edit-resume` / `manage-unauthenticated-viewers` pattern.
- [x] 6.4 `markSpam` sets `is_spam = true` and `approved_at = null` in one operation; `markNotSpam` sets `is_spam = false` and `approved_at = now()`.
- [x] 6.5 Build `resources/js/admin/pages/comments/Index.tsx` reusing `DataTable`, `Pagination`, `ConfirmDialog`, `StatusChip`, `EmptyTableRow`, and `PageHeader` from `resources/js/admin/components/`.
- [x] 6.6 Add the `/admin/comments` entry to `resources/js/admin/navigation.json` under the `/admin` route block, matching the existing `can` / `href` / `icon` / `label` / `description` shape.
- [x] 6.7 Feature tests: permitted user sees the queue, unpermitted denied, mark-spam hides from public thread and retains the row, mark-not-spam restores with a fresh `approved_at`, and no path produces `approved_at` non-null with `is_spam` true.

## 7. Documentation

- [x] 7.1 Rewrite the "Comment System" section of `CLAUDE.md`. It currently points at a `docs/comments/` directory that does not exist, says post associations still need a migration when that migration is applied, and lists the Facebook webhook and email notifications as working.
- [x] 7.2 Remove the `/mlopnadjs22tn` entry from `CLAUDE.md`'s "Security Features".
- [x] 7.3 Note in `CLAUDE.md` that the app now uses the queue, since this is its first queued work.

## 8. Sync specs

- [x] 8.1 Run `/opsx:sync replace-facebook-comments-with-native` to merge the four delta specs into `openspec/specs/`.

## 9. Manual verification

- [x] 9.1 Post an anonymous comment and a reply chain 5 deep on a local post; confirm the depth-5 comment offers no reply control and that indentation stops widening after level 2.
- [x] 9.2 Mark a mid-thread comment as spam; confirm the tombstone appears and its replies stay at their original depths. Restore it and confirm it reappears.
- [ ] 9.3 With the queue worker running, post a comment and confirm the response returns before the mail sends and that a row appears in `jobs`. This is the first queued work in the app — verify the worker actually drains it rather than assuming. **Partially done:** a rolled-back probe confirmed the job is enqueued to the `database` driver with a `CommentReceivedMail` payload rather than sent inline. Still needs a running worker to confirm it drains.
- [x] 9.4 Confirm production's `.env` sets `QUEUE_CONNECTION=database` at deploy. If it stays `sync`, notifications silently keep sending inline. **Confirmed by the developer 2026-09-03: production is now `database`.**
- [x] 9.5 Grep the rendered HTML for `facebook` and confirm no SDK, `#fb-root`, or `fb:app_id` remains.
- [x] 9.6 Run `php artisan test --compact` for the comment tests, then ask whether to run the full suite. Use `DatabaseTransactions`, never `RefreshDatabase` — the test connection points at a real database.

## 10. Schema drift found during verification

- [x] 10.1 `comments.post_id` was `bigint unsigned` in `jasonvertucio` but `char(36)` in `wink`. `2025_08_30_165525_add_post_reference_to_comments.php` guards its entire body with `if (! Schema::hasColumn('comments', 'post_id'))`, and a `post_id` column already existed in the app database, so the migration skipped — never retyping the column and never adding the foreign key. Canvas post ids are UUIDs, so every comment insert in the app database truncated the id and failed. The test suite could not have caught this: it runs against `wink`, which was correct. Added `2026_09_03_000334_correct_comments_post_id_type.php`, which retypes the column and adds the missing foreign key idempotently, and applied it to both databases.
