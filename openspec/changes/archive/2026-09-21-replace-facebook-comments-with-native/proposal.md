## Why

The site's comment system is documented in `CLAUDE.md` as partly working. It is not. An audit found:

- **No comment data exists anywhere.** `jasonvertucio.comments`, `wink.comments`, and the production table are all empty. `storage/logs` contains zero `plugin_comment` payloads. There is nothing to migrate.
- **The Facebook capture path could never have worked.** `CommentObserver::created()` sends `CommentReceivedMail`, whose template dereferences `$comment->post->title` — but `FacebookCallbackController` never sets `post_id`. Every successful insert would throw on the null relation, inside the request.
- **A column-name typo silences reply threading.** `Comment::$fillable` lists `fb_parent_comment_id`; the actual column is `fb_comment_parent_id`. The controller writes the real name, which is not fillable, so mass assignment drops it. `CommentFactory` writes the typo'd name, which is not a column, so it errors on insert.
- **A foreign key blocks any import.** `fb_user_id` carries an FK to `users.id`. A Facebook user id is not a site user id; the constraint rejects every legacy row.
- **Display is 100% Facebook.** `blog/single.blade.php` renders a `fb-comments` div and `layout.blade.php` loads `connect.facebook.net/sdk.js` at **v9.0** (2020) with a hardcoded app id. There is no submission path, no rendering of the `comments` table, and no moderation anywhere.

Facebook is no longer the plan. Because there is no data to preserve, this is a greenfield build rather than a migration, and the Facebook capture path can be deleted outright rather than kept alive pending a recovery that has no source.

## What Changes

**Remove Facebook.** Delete the `fb-comments` div, the `fb:app_id` meta tag and its `env()` call, the `#fb-root` element and SDK script, the `/mlopnadjs22tn` webhook route, and `FacebookCallbackController`.

**Build native commenting.** Anonymous and authenticated commenting on blog posts, threaded to a hard maximum depth of 5, auto-approved on creation, rendered from the `comments` table.

**Add moderation.** A "mark as spam" action that clears `approved_at` and sets `is_spam`, reversible via "not spam" which restores `approved_at` to the current time. A spam-hidden comment renders as a tombstone so its replies survive. Moderation lives at `/admin/comments` as an Inertia/React page, consistent with the rest of the admin panel.

**Fix and queue notifications.** `CommentReceivedMail` becomes queued, addresses a configurable recipient, skips comments authored by the site owner, and no longer breaks on a missing post.

## Capabilities

### New Capabilities

- `blog-comment-submission`: who may comment, what a submission must contain, how nesting depth is enforced, and how abuse is resisted.
- `blog-comment-display`: how an approved comment tree is queried, ordered, indented, and tombstoned.
- `blog-comment-moderation`: the approved/spam state machine and the admin surface that drives it.
- `comment-notification-delivery`: who is notified of a new comment, through what transport, and when notification is suppressed.

### Removed Capabilities

None — the Facebook integration was never captured as a spec.

## Decisions

Settled during exploration and not open for re-litigation while implementing:

| Question | Decision |
| --- | --- |
| Anonymous commenting | Stays |
| Identity modes | Registered, legacy Facebook, and anonymous all coexist in one table |
| Nesting depth | Hard maximum of 5 |
| Approval | Auto-approve on create |
| Spam action | Sets `is_spam`, clears `approved_at` |
| Un-spam action | Restores `approved_at = now()` |
| Hidden mid-thread comment | Tombstone; children survive in place |
| Visual indent on mobile | Capped at 2 levels regardless of true depth |
| Owner's own comments | Do not send a notification email |
| Notification transport | Queued; `QUEUE_CONNECTION` moves from `sync` to `database` |
| Moderation UI | `/admin/comments`, Inertia/React |

## Impact

**Schema** — `comments` table: add `approved_at`, `is_spam`, `depth`, `ip_address`, `user_agent`; drop the `fb_user_id → users.id` FK and retype the column; replace the `parent_id` `onDelete('set null')` constraint, which silently flattens replies to root and staleness `depth`.

**Application-wide** — `QUEUE_CONNECTION` changes from `sync` to `database`. No class in `app/` currently implements `ShouldQueue`, so the supervisord `queue:listen` worker (already configured in both `docker/supervisord.prod.conf` and `docker/supervisord.dev.conf`) has never processed a job. Comments will be the first real exercise of that path in production, and production's `.env` must be updated at deploy time.

**Documentation** — `CLAUDE.md`'s "Comment System" section is stale in three ways: it references a `docs/comments/` directory that does not exist, claims post associations need a migration when that migration is already applied, and lists the Facebook webhook and email notifications as working. Its "Security Features" entry for `/mlopnadjs22tn` also goes away.

**Constraint discovered during exploration** — moderation cannot live at `/canvas/comments`. Canvas registers `Route::get('/{view?}', 'ViewController')->where('view', '(.*)')` at router index 38; `routes/web.php` begins at index 67. The greedy catch-all swallows every path under `/canvas` before any app route is consulted. This is the same failure mode already documented at `routes/web.php:38-41` for code-talker's root wildcard.
