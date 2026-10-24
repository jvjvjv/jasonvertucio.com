## Why

The blog previously used Facebook's Comments Plugin (an embedded iframe/SDK widget:
`<div class="fb-comments" data-href="...">`), which stored comment content, authorship,
and threading entirely on Facebook's servers, keyed by the post's URL. That widget was
removed when the native comment system shipped. The `comments` table already has
`fb_user_id`, `fb_comment_id`, and `fb_comment_parent_id` columns reserved for this, but
every row from that era exists only on Facebook — nothing was ever stored locally.
Historical comments left by readers on old blog posts are therefore invisible on the
site today unless they are pulled from Facebook and imported into the native system.

## What Changes

- Add a one-off/repeatable Artisan command that, for each blog post, calls the Facebook
  Graph API's comments edge for that post's URL (`GET /{url-encoded-page-url}/comments`,
  or the Page's Open Graph object id for the URL) and imports the returned comments as
  `Comment` rows.
- Map each Graph API comment to a `Comment` row:
  - `fb_comment_id` (Graph API comment `id`) for idempotent re-runs / dedup.
  - `fb_comment_parent_id` (Graph API parent comment `id`, if a reply) resolved to the
    imported comment's local `parent_id`, with `depth` computed the same way
    `StoreCommentRequest` computes it today, capped at `Comment::MAX_DEPTH`.
  - `name` snapshotted from the Graph API `from.name` (the commenter's Facebook display
    name at time of import — Facebook does not expose an email for plugin commenters).
  - `email` left null; imported comments have no site account and no email to notify.
  - `message` from the Graph API comment `message`.
  - `created_at` from the Graph API comment `created_time`, so imported comments sort
    into their original chronological position instead of appearing "new".
  - `approved_at` set to the same `created_at` (Facebook-hosted comments were already
    publicly visible for years; re-review of years-old public comments is not useful),
    `is_spam` false.
  - `user_id` null (no site account association is possible for a Facebook identity).
- A comment whose Graph API parent cannot be resolved (parent not returned, e.g.
  deleted on Facebook, or nesting deeper than Facebook's own 2-level reply limit) is
  imported as a top-level comment rather than dropped, so no content is silently lost.
- Command is safe to re-run: an `fb_comment_id` already present in `comments` is
  skipped rather than duplicated.
- **BREAKING**: none. This is additive — no existing comment, route, or model
  behavior changes; it only writes new rows into the existing `comments` table.

## Capabilities

### New Capabilities
- `legacy-facebook-comment-import`: an Artisan command that fetches historical
  Facebook Comments Plugin comments per blog post via the Graph API and imports them
  as native, pre-approved `Comment` rows, safe to re-run without duplicating rows.

### Modified Capabilities
(none — the display, moderation, and submission specs for the native comment system
are unchanged; imported comments are ordinary `Comment` rows that flow through the
existing `visible()` scope, thread rendering, and moderation UI without modification)

## Impact

- **New**: an Artisan command (e.g. `php artisan comments:import-facebook`) and a
  small Graph API client/service to fetch comments per post URL.
- **Config/credentials**: requires a Facebook App ID/secret (or a long-lived Page
  access token) with permission to read Comments Plugin data for the site's domain,
  which does not currently exist in `config/` or `.env`. This must be obtained via
  Meta's developer/Business tools before the command can run — see design.md for the
  concrete availability check to run first, since older Comments Plugin threads are
  not guaranteed to still be retrievable.
- **Data**: writes to the existing `comments` table only; no migration needed (the
  `fb_user_id`, `fb_comment_id`, `fb_comment_parent_id` columns already exist).
- **No changes** to `CommentController`, `StoreCommentRequest`, `CommentObserver`,
  moderation controllers/UI, or comment display components.
