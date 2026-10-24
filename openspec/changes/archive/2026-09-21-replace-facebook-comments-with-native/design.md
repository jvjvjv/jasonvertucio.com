## Context

Comments have existed as a table since 2021 and as a Facebook widget for as long. Neither half ever met the other: the table is empty, and the widget's data lives on Facebook's servers keyed by URL. Removing Facebook therefore does not strand any data, which is what makes a clean rebuild affordable.

Three constraints shape the design:

1. **Three identity modes must coexist in one table** — registered, legacy Facebook, anonymous.
2. **Depth is capped at 5**, which is deep enough that hiding a node mid-thread has consequences.
3. **The admin panel is entirely Inertia/React.** There is no `resources/views/admin/` directory; `AdminController`, `MailPreviewController` and `ResumeShareCodeController` all `return Inertia::render(...)`.

## Goals / Non-Goals

**Goals**
- Native comment submission, display, and moderation with no third-party dependency.
- Spam control that reuses the existing `IpBan` / `IpMiddleware` infrastructure rather than adding a service.
- Notification email that actually sends, off the request path.

**Non-Goals**
- Importing Facebook comments. No source exists. The Graph API's Comments-Plugin edge is believed removed as of Graph v3.0, and neither the production database nor the logs hold anything.
- Editing comments after posting.
- Reactions, votes, or sorting modes beyond chronological.
- Replacing Canvas or relocating its route prefix.

## Decisions

### Identity: snapshot the display name

```
user_id      char(36) NULL  FK → users.id        registered
fb_user_id   varchar  NULL  no FK                legacy import
name         varchar  NOT NULL                   always populated
email        varchar  NULL                       anonymous / FB
```

`name` is written on every comment regardless of mode, as a **snapshot** of the display name at posting time. Rendering never branches on identity mode, the row survives user deletion, and a future Facebook import lands uniformly.

`fb_user_id`'s foreign key to `users.id` is dropped. A Facebook user id is a numeric string, not a UUID, and pointing it at the site's user table was always wrong — it would reject every row it was meant to hold.

**Alternative considered:** joining to `users` for the display name of registered commenters. Rejected because it makes rendering conditional on identity mode and couples comment display to user lifecycle.

### Nesting: adjacency list plus a denormalized depth

```
┌─────────────────────┬──────────────────────┬───────────────────────┐
│ ADJACENCY + depth   │ MATERIALIZED PATH    │ RECURSIVE CTE         │
├─────────────────────┼──────────────────────┼───────────────────────┤
│ parent_id           │ path = "3/17/42/"    │ parent_id only        │
│ depth  TINYINT      │ depth derivable      │ WITH RECURSIVE ...    │
│ 1 query, tree in PHP│ 1 query, sort by path│ DB does the walk      │
│ ✓ trivial to import │ ✗ path rewrite on    │ ✗ MySQL 8 only,       │
│ ✓ trivial to reason │   any re-parent      │   harder to test      │
└─────────────────────┴──────────────────────┴───────────────────────┘
```

**Chosen: adjacency list with a denormalized `depth` tinyint.** At 27 published posts with low comment volume, one `where post_id = ?` query loading the full thread and assembling it in PHP is correct and obvious. Materialized path only pays off when arbitrary subtrees must be fetched without loading the whole thread, which will never happen here.

`depth` is computed on insert as `parent.depth + 1` and validated `<= 5`. It is denormalized, so anything that re-parents a comment must recompute it — which is why the existing FK must change (below).

### The `parent_id` foreign key must change

`create_comments_table.php:23` declares `onDelete('set null')`. On a hard delete of any comment, every reply silently jumps to root level and its `depth` column goes stale — a depth-5 thread collapses to depth 0 with no error. Since tombstoning (below) means a hidden comment's row is retained anyway, the cascade behavior should be `restrict` or the deletion path should re-parent deliberately and recompute `depth`.

### Hiding: tombstone, not cascade

```
  A (ok)                 depth 0
  └─ B [comment removed]  depth 1   ← is_spam, approved_at NULL
     └─ C (ok)            depth 2   ← survives, still at depth 2
        └─ D (ok)         depth 3
```

The row is retained, its body is not rendered, and its children keep their positions. Cascade would let a single spam reply take out a legitimate sub-thread; re-parenting mutates real data on a moderation action and is awkward to reverse.

In practice the three strategies are indistinguishable most of the time — spam bots overwhelmingly post leaf replies with no children — but tombstone is the least destructive when they do differ, and it is not meaningfully harder to build.

### State: `approved_at` is authoritative, `is_spam` is the reason

```
                        ┌────────────────────┐
    comment created     │      APPROVED      │   approved_at = now()
    ───────────────────▶│     is_spam = 0    │   is_spam     = false
                        └─────────┬──────────┘   ══▶ VISIBLE
                                  │
                    "Mark spam"   │   ▲   "Not spam"
                                  ▼   │   approved_at = now()
                        ┌────────────────────┐
                        │       SPAM         │   approved_at = NULL
                        │     is_spam = 1    │   is_spam     = true
                        └────────────────────┘   ══▶ TOMBSTONE
```

Public visibility predicate: `whereNotNull('approved_at')->where('is_spam', false)`, plus the `SoftDeletes` global scope on `deleted_at`.

With auto-approve on, these two columns are presently redundant — `approved_at IS NULL` and `is_spam = true` describe the same set, always. The third state (`approved_at` null, `is_spam` false, i.e. "held for review") is currently unreachable and the fourth (`approved_at` set with `is_spam` true) is contradictory and should be prevented. Both columns are kept anyway: `is_spam` records *why* something is hidden, enables spam reporting, and leaves room for a hold-for-review state later without another migration.

**`approved_at` is what the display query trusts.** `is_spam` must never be the sole basis for a visibility decision.

### Rendering: visual indent decouples from data depth

```
  data depth:    0    1    2    3    4    5
  visual indent: 0    1    2    2    2    2
                           └──────────────┘
                    flattened; "in reply to @name" backlink
```

Five levels of indentation is unusable on a phone. True depth is preserved in the data and in the reply-eligibility check (the reply control is hidden at depth 5); only the visual offset is capped at 2, with deeper comments carrying an explicit backlink to their parent so the thread stays followable.

### Abuse: reuse what exists

The site already owns `IpBan`, a Cloudflare-aware `IpMiddleware`, and the `routes/honeypots.php` pattern. Anonymous commenting on a public blog with no Facebook shield needs all of it:

```
  submit ──▶ honeypot field ──▶ rate limit ──▶ store (auto-approved)
                  │                 │
                  ▼                 ▼
              silent 200      429 + IpBan candidate
```

`ip_address` and `user_agent` are recorded on every comment so a spam determination can feed `IpBan`. No third-party spam service is introduced.

### Notification: queued, configurable, self-suppressing

`QUEUE_CONNECTION` is `sync` today, and **no class in `app/` implements `ShouldQueue`** — so adding the interface alone would change nothing observable, and the mail would still send inline. The connection must move to `database`; the `jobs` and `failed_jobs` tables already exist, and `docker/supervisord.prod.conf` and `docker/supervisord.dev.conf` already run `queue:listen`.

`queue:listen` reboots the framework per job, so it picks up new code without a `queue:restart` step in `npm run pull`.

Recipient comes from a new `config/comments.php`, following the `config/resume.php` precedent, since project rules forbid `env()` outside config:

```php
'notification_email' => env('COMMENT_NOTIFICATION_EMAIL', 'me@jasonvertucio.com'),
```

Four bugs are fixed alongside: the null `$comment->post` dereference, the missing `mailto:` scheme on the address link in `mail/new-comment.blade.php`, the `fb_parent_comment_id` fillable typo, and suppression of notifications for the site owner's own comments.

### Moderation UI: `/admin/comments`, Inertia/React

`/canvas/comments` was the original preference and is not reachable — Canvas's `Route::get('/{view?}')->where('view', '(.*)')` registers at router index 38, ahead of `routes/web.php` at index 67, and swallows the entire prefix.

Alternatives weighed: a standalone Blade section outside `/admin`; forcing route registration ahead of Canvas's provider; relocating `config('canvas.path')`. The first splits the admin across two paradigms, the second breaks silently on any Canvas upgrade, and the third breaks bookmarks for no gain.

`/admin/comments` reuses the existing gate group in `routes/admin.php`, the `navigation.json` entry format, and the ready-made `DataTable`, `Pagination`, `ConfirmDialog`, `StatusChip`, `EmptyTableRow`, and `PageHeader` components in `resources/js/admin/components/`.

## Risks / Trade-offs

- **`QUEUE_CONNECTION=database` is a system-wide flip.** Everything queued afterwards becomes genuinely asynchronous. Nothing is queued today, so blast radius is small — but it is the first production exercise of that worker and deserves a smoke test rather than an assumption.
- **Production `.env` must change at deploy.** It is `sync` there too, and a missed edit means notifications silently keep sending inline. Not broken, but not what was specified.
- **Anonymous commenting invites spam.** Honeypot plus rate limiting plus `IpBan` is a reasonable first line, but a determined bot will get through. Because comments auto-approve, spam is publicly visible until manually marked. The hold-for-review state that `is_spam` leaves room for is the escape hatch if this becomes a problem.
- **Tombstones leak that something was removed.** Accepted as strictly better than orphaning replies.

## Open Questions

- Should the FB columns (`fb_user_id`, `fb_comment_id`, `fb_comment_parent_id`) be retained at all? Nothing has ever populated them, and no import source has been identified. They are kept for now because legacy Facebook identity was named as one of the three identity modes — but if recovery is abandoned outright, dropping them simplifies the schema.
- Should the notification email carry a one-click "mark as spam" link? Comments auto-approve and notify on creation, so spam reaches the inbox before it can be moderated. A signed URL would close that loop.
