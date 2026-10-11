## Why

A comment is permanent the moment it is posted: a registered commenter cannot fix a typo or withdraw something they regret, and the site owner's only tool is "mark as spam", which mislabels a comment that is merely unwanted. Comments already record `user_id` and the model already soft-deletes, so ownership and removal are both half-built and unused.

## What Changes

- A logged-in user can **edit the message** of a comment they wrote, and **delete** it, from the post page.
- A user holding `manage-blog` (the permission that already gates the moderation queue — "admin" throughout this change) can edit and delete **any** comment.
- **Guests get neither.** An anonymous comment has no `user_id`, so nobody but an admin can edit or delete it — not the visitor who wrote it, and not a logged-in user whose email happens to match. Comments whose author account was later deleted fall into the same bucket.
- Only the `message` is editable. `name`, `email`, the post, the parent and the depth are fixed at posting time.
- An edited comment shows an **"(edited)"** marker on the public thread, driven by a new `edited_at` column. It is set on any real change to the message, including an admin's edit of someone else's comment.
- Deleting is a **soft delete**. A deleted comment that still has displayable replies renders as `[comment removed]`, exactly as a spam comment does today; a deleted leaf disappears. Replies keep their positions and depths.
  - **BREAKING** (spec-level): the current requirement says a soft-deleted comment is absent "with no tombstone". In practice that also makes every reply beneath it vanish, because the tree is assembled by `parent_id` and the deleted parent is never loaded. That requirement and its test are replaced.
- A reply aimed at a deleted comment is refused. Today it passes validation and is silently posted as a top-level comment.
- The "in reply to" backlink no longer names the author of a removed comment.
- The moderation queue at `/admin/comments` gains a **Delete** action, because a spam comment is hidden on the post page and so offers no controls there.

Out of scope: restoring a deleted comment, an edit history, an edit time window, editing from the moderation queue, and hard deletion.

## Capabilities

### New Capabilities
- `blog-comment-editing`: who may edit or delete a comment, what an edit may change, and what deleting does to the row.

### Modified Capabilities
- `blog-comment-display`: a soft-deleted comment with displayable replies now tombstones instead of taking its replies with it; edited comments carry a marker; edit/delete controls are shown only to those who may use them; the backlink stops naming a removed comment's author.
- `blog-comment-moderation`: the queue offers a delete action per comment.
- `blog-comment-submission`: a reply to a deleted comment is refused.

## Impact

- **Schema**: one migration adding nullable `comments.edited_at`. It must be run against both `jasonvertucio` and `wink`.
- **Routes**: `PUT` and `DELETE /blog/{slug}/comments/{comment}` (authenticated); `DELETE /admin/comments/{comment}` (`manage-blog`).
- **Code**: `Comment` model, a new `CommentPolicy` (the app's first policy), `CommentController`, a new `UpdateCommentRequest`, `StoreCommentRequest`, `CommentModerationController`, `CommentThread`/`CommentNode` and their Blade templates, `resources/js/admin/pages/comments/Index.tsx`.
- **Tests**: `CommentDisplayTest::test_soft_deleted_comments_are_absent_with_no_tombstone` encodes the behaviour being replaced and must be rewritten, not deleted; new feature tests for editing and deleting.
- **Docs**: the *Comment System* section of `CLAUDE.md`.
- No new dependencies. No notification is sent on edit or delete — `CommentObserver` only listens for `created`.
