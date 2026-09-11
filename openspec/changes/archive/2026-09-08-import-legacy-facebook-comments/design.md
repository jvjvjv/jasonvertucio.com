## Context

See proposal.md - Why. The `comments` table already carries `fb_user_id`,
`fb_comment_id`, and `fb_comment_parent_id` (added in
`2025_08_30_165813_add_user_information_to_comments.php`), fillable on `Comment`, but
unused since the Facebook Comments Plugin widget was removed — nothing populates them
today. Depth/threading precedent lives in `CommentController::store()`
(`depth = parent === null ? 0 : parent->depth + 1`) and `Comment::MAX_DEPTH` (5).

Facebook's Comments Plugin stores comments against the exact page URL passed to
`data-href`, historically `Request::url()` for `blog/{slug}` at whatever domain was live
at the time (verify - the site may have moved domains/URL structure since 2020, which
would change what URL to query). The Graph API comments edge for a URL is
`GET /?id={url-encoded page URL}&fields=comments{...}` (or the object id if the URL
resolves to one) and requires an access token: a Page token is preferred over a user
token since it does not expire on a fixed schedule.

## Goals / Non-Goals

**Goals:**
- Recover comment content Facebook still has, without Facebook credentials becoming a
  permanent runtime dependency (the import is a one-off/occasionally-repeated batch
  job, not a live sync).
- Leave every existing comment code path (`CommentController`, `StoreCommentRequest`,
  `CommentObserver`, moderation, display) untouched — imported rows must be
  indistinguishable from native ones to every consumer except the `fb_*` columns.

**Non-Goals:**
- Live/ongoing sync from Facebook. Facebook's Comments Plugin is gone from the site; no
  new comments will ever appear there to sync.
- Importing Facebook *reactions* (likes) on comments — out of scope, no local concept
  of comment reactions exists.
- Matching imported commenters to existing site `User` accounts. Facebook identity has
  no reliable link to a local account (no shared email); `user_id` stays null.

## Decisions

**Availability check before building the importer.** Before writing the import
command, manually verify (via Graph API Explorer or a quick script) that at least one
known old post's comments are still retrievable from the Graph API using the site's own
credentials. Facebook comments plugin data becomes unretrievable if: the domain is no
longer verified in Meta Business settings, the app/page association was removed, or
Facebook purges plugin data for inactive domains. If nothing is retrievable, this
change stops at "not feasible" rather than building an importer with nothing to import
— **this is a task in tasks.md, not deferred**, since it decides whether the rest of
the work is worth doing at all.

**Command shape: idempotent Artisan command, run manually.** `php artisan
comments:import-facebook [--post=<slug>]` iterates published blog posts (optionally
scoped to one via `--post`), calling the Graph API per post. Not scheduled — this is a
recovery operation run by the developer once (or a few times while iterating), not
ongoing infrastructure. No queue job: Graph API pagination for a single post's comment
thread is small (blog posts had light engagement) and a synchronous command with
per-post error isolation is simpler to reason about and re-run than a queued batch.

**Dedup key: `fb_comment_id`.** Query `Comment::where('fb_comment_id', $graphId)`
before inserting; skip if found. Simpler and more direct than a separate "import run"
ledger, and it is exactly the column the schema already reserved for this.

**Threading resolution: two-pass per post.** First pass creates every top-level Graph
API comment (no `parent_id`) for the post; second pass creates replies, looking up
`parent_id` by matching each Graph API comment's `parent.id` against the just-created
`fb_comment_id` values for that post. The Graph API returns a reply's parent id
directly, so no need to reconstruct threading from timestamps. A comment whose parent
isn't found this way (deleted parent, or a Facebook reply chain beyond what the plugin
exposed) is created top-level per the spec, not dropped.

**Depth cap:** if resolving the full parent chain would put a comment past
`Comment::MAX_DEPTH`, it is created top-level rather than truncated mid-thread — Graph
API-exposed reply nesting for the Comments Plugin was shallow (effectively 1 level) in
practice, so this is a defensive cap, not an expected path.

**Timestamps: `created_at`/`updated_at` set explicitly from Facebook's
`created_time`.** Requires `Comment::unguard()` or explicitly allowing timestamp
mass-assignment for the import (Eloquent normally manages timestamps itself) — both
columns are already in `$fillable`, so this is a matter of passing them through
`forceFill()`/`create()`, not a schema change.

**Credentials:** a Page access token read from config (new `services.facebook.*` keys
sourced from `.env`, following the existing "no `env()` outside config files"
convention), used only by this command. Not stored anywhere else, no `AppServiceProvider`
wiring needed beyond the config file.

## Risks / Trade-offs

- [Facebook has purged/hidden the data] → Availability check decision above catches
  this before any importer code is written.
- [Site's blog URL structure changed since the comments were made, so the Graph API
  lookup URL doesn't match what Facebook indexed] → Command accepts an explicit URL
  override per post (or a mapping table) for posts where the canonical `route()` URL
  doesn't match; document this as a task, not a blocking design decision, since it's
  only discoverable once the availability check runs against real data.
- [Long-lived Page tokens still expire eventually] → Acceptable: this command is not
  run on a schedule, so a token refresh (manual, via Meta Business tools) only matters
  if the command needs to run again.
- [Facebook display names may collide with, or be confused for, real site users] →
  Names are stored as free-text `name` exactly as native anonymous comments already are
  (no uniqueness or account-linking guarantee exists for anonymous names today), so
  this introduces no new behavior.

## Migration Plan

No schema migration — target columns already exist. Rollout is: run the availability
check, then run the command against production (or a copy) once, spot-check imported
threads on a couple of posts, then re-run for any posts that failed transiently.
Rollback is deleting the imported rows (`Comment::whereNotNull('fb_comment_id')`) if
something is wrong with a run — no other system references them yet.

## Open Questions

- Exact historical URL(s) each old post was commented under (may require checking
  Wayback Machine or old deploy history if it's not simply today's `route()` URL) —
  resolved during the availability-check task, doesn't change the approach above.
