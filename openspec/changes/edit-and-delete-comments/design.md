## Context

See `proposal.md` for motivation. What shapes the approach:

- `Comment` already uses `SoftDeletes` and records `user_id` (null for anonymous comments). Nothing deletes a comment today.
- `CommentThread::tree()` loads the post's comments with the default scope, so a soft-deleted row is never loaded. `branch()` walks by `parent_id`, so the replies under a deleted comment match no branch and silently vanish. The existing spec ("absent … with no tombstone") and `CommentDisplayTest::test_soft_deleted_comments_are_absent_with_no_tombstone` only ever exercised a leaf.
- `Comment::isVisible()` is the single predicate the node template and the tree pruning both use, and it knows nothing about `deleted_at`.
- `StoreCommentRequest` validates `parent_id` with `exists:comments,id`, which ignores soft deletes, then resolves the parent with `Comment::find()`, which respects them. For a deleted parent the rule passes, `parentComment()` returns null, and the controller stores a top-level comment.
- The public thread is plain Blade with no page JavaScript; the reply form is a native `<details>`. The moderation queue is an Inertia/MUI page.
- Authorization today is route-level `can:manage-blog` only. There is no `app/Policies` directory. Keystone registers a `Gate::before` that returns `true` for a super-admin, answers definitively for any ability whose name matches a Keystone permission (one uncached query per check), and otherwise returns `null` so policies run.
- `markSpam`/`markNotSpam` call `update()`, so `updated_at` moves on moderation and cannot stand in for "was edited".

## Goals / Non-Goals

**Goals:**
- One ownership rule, used by both the endpoints and the thread's controls, so the two cannot drift.
- Deleting never changes what happens to other people's replies.
- No new JavaScript on the public blog.

**Non-Goals:**
- Restoring a deleted comment, or listing deleted comments in the queue. The row is retained (with its IP and user agent), so this can be added later without a migration.
- Recording who edited, or keeping prior versions of a message.
- An edit window or an edit rate limit. Edits are authenticated, and sharing the `comments` limiter would let editing consume a user's posting allowance.
- Editing from the moderation queue. An admin edits on the post page, in context.
- Hard deletion. `parent_id` is `restrictOnDelete`, which is what protects threads; nothing here touches it.

## Decisions

### 1. The rule lives on the model; the policy and the thread both call it

`Comment::isManageableBy(?User $user, bool $isAdmin): bool` is the whole rule: an admin may manage any comment; otherwise the user must exist, `user_id` must be non-null and equal to the user's id, and the comment must be `isVisible()`. `CommentPolicy::update()` and `delete()` are one-liners that resolve `$isAdmin` as `$user->can('manage-blog')` and delegate. Edit and delete share one rule today; they get separate policy methods so they can diverge without touching call sites.

`CommentThread` resolves the viewer and their admin status **once** and hands both to each `CommentNode`, which calls the same model method. The template does not use `@can('update', $comment)`.

*Why not `@can` per node:* every Gate check runs Keystone's `before` callback, which issues a permission-name lookup. Two abilities per comment on a 50-comment thread is 100 extra queries for a page that today costs one.

*Why a policy at all, rather than an inline check:* `authorize()` in the form request and controller is the Laravel-native seam, it answers 403 uniformly, and the policy is auto-discovered (`App\Policies\CommentPolicy` for `App\Models\Comment`) with no registration.

*Alternative rejected — matching on email for anonymous comments:* email is unverified at submission, so it is a claim, not an identity. This is the explicit "guests never" requirement.

### 2. Routes are nested under the post and require `auth`

```
PUT    /blog/{slug}/comments/{comment}   comments.update
DELETE /blog/{slug}/comments/{comment}   comments.destroy
```

Both sit in the existing `blog` group next to `comments.store`, behind `auth`, so a guest is redirected to login rather than shown a 403. `{comment}` uses implicit binding (a trashed comment therefore 404s on its own). The controller additionally 404s when the comment's post does not have the slug in the URL — the slug is not decorative, it is where the redirect lands. The two-segment suffix cannot collide with `GET /blog/{slug}`.

Blade forms use `@method('PUT')` / `@method('DELETE')`.

The queue gets its own `DELETE /admin/comments/{comment}` on `CommentModerationController`, inside the existing `manage-blog` group, returning `back()` like its siblings. *Why not reuse the public endpoint:* that one redirects to the post page, and the queue must stay on the queue. Both call `$comment->delete()`; there is no shared service because there is nothing else to share.

### 3. `edited_at`, set by the controller only when the message really changes

A nullable `edited_at` timestamp, added after `approved_at`, cast to `datetime`, with `isEdited()` on the model. `CommentController::update()` fills the message and, if `isDirty('message')`, sets `edited_at = now()` before saving. `edited_at` is deliberately **not** added to `$fillable` — nothing should mass-assign it.

*Why not `updated_at != created_at`:* spam/not-spam moves `updated_at`, so every restored comment would read as edited.

*Why not a model `updating` hook:* the controller is the only writer of `message`, and an explicit line is easier to find than an event.

### 4. `UpdateCommentRequest` validates only `message`

Same rule and messages as the store request (`required|string|max:5000`). `authorize()` returns `$this->user()->can('update', $this->route('comment'))`. The controller reads `message` alone, so extra fields are inert rather than rejected.

A failed edit must reopen the right form on a page that may hold dozens of them. The edit form carries a hidden `editing` field holding the comment id; the node treats itself as the target when `old('editing')` matches, opens its `<details>`, and shows `old('message')` and the error. The request overrides `getRedirectUrl()` to append `#comment-{id}`. Errors go to a named error bag (`commentEdit`) so an edit failure is not rendered by the new-comment form's `@error('message')`, which is guarded by `old('parent_id')` matching and would otherwise match the root form (`null === null`).

### 5. Deleted comments join the tree as tombstones

- `CommentThread::tree()` adds `withTrashed()`. Still one query.
- `Comment::isVisible()` becomes "approved, not spam, **and not trashed**". Everything downstream already keys off it: the node template's tombstone branch, the leaf pruning in `branch()`, and the ownership rule in decision 1. `scopeVisible()` needs no change — the soft-delete global scope already applies to it, so `visibleCount()` stays correct.
- The tombstone markup and the `[comment removed]` text are shared with spam on purpose: a reader should not be able to tell a withdrawn comment from a moderated one.

*Alternative rejected — a `deleted` flag separate from soft delete:* it would duplicate `deleted_at` and need its own exclusion in every query that today gets it free.

**Consequence to check during implementation:** any other reader of `isVisible()` now also excludes trashed rows. Today those are `comment-node.blade.php` and `CommentThread::branch()` only, both of which want exactly that.

### 6. The backlink stops naming a hidden parent

`comment-node.blade.php` prints `$comment->parent->name` for any comment deeper than the visual cap. With `withTrashed()` the in-memory parent map now contains deleted parents, so this would print a deleted author's name — and it already prints a spam author's. When the parent is not `isVisible()` the link reads "a removed comment" and still targets `#comment-{id}`, which is the tombstone's anchor. This is a small fix to existing spam behaviour, made here because the same line would otherwise introduce the leak for deletions.

### 7. Replies to a deleted parent fail validation

Replace the bare `exists:comments,id` with `Rule::exists('comments', 'id')->whereNull('deleted_at')`, with a message of its own ("That comment is no longer available."). The reply's text comes back through the existing `old()` handling.

Replying to a *spam* parent stays as it is: reachable only from a stale form, and not something this change was asked to alter.

### 8. Controls are native HTML

Inside the visible comment's `<article>`, beside the existing Reply `<details>`:

- **Edit** — a `<details>` holding a textarea pre-filled with the message and a Save button.
- **Delete** — a `<details>` whose summary is "Delete" and whose body is a one-line warning and a "Delete comment" submit button. Opening the disclosure is the confirmation step, so no `confirm()` and no script.

Styling follows the existing node (Tailwind utilities already in use; no gradients, no shadows; `focus-visible` outlines as on the reply form). The "(edited)" marker sits in the header after the timestamp, in the timestamp's muted style, with the edit time in a `title`/`<time>` so it is available without being loud.

In the queue, Delete is a third row action using the existing `useConfirmDialog`, shown for every row regardless of spam state.

### 9. Redirects

- Update → the post, `#comment-{id}`, flashing `comment_updated`.
- Delete from the post → the post, `#comments`, flashing `comment_deleted` (the comment's own anchor may no longer exist).

`comment-thread.blade.php` renders each flash in the same banner style as `comment_posted`.

## Risks / Trade-offs

- **An admin can reword someone's comment and readers see only "(edited)"** → Accepted for a single-owner blog; the marker at least signals the text is not original. Recording `edited_by` is a column away if it is ever wanted.
- **A user can delete a comment others have replied to, leaving replies answering nothing** → The tombstone keeps the thread's shape and makes the gap visible; blocking deletion was considered and declined.
- **Tombstones can accumulate** → A hidden branch with no displayed descendant is pruned, so a thread deleted from the bottom up disappears entirely.
- **A Keystone permission named `update` or `delete` would hijack the policy**, because `Gate::before` answers for any ability matching a permission name → Permission names here are all hyphenated verb-noun (`manage-blog`, `edit-resume`); a feature test asserting a non-owner gets 403 and an owner gets through would fail loudly if one were ever added.
- **Super-admins count as admins without holding `manage-blog`** (the `before` bypass) → Intended; because the thread asks `can('manage-blog')` rather than `hasPermissionTo()`, the controls and the endpoints agree.
- **The replaced display test** → It must be rewritten to assert the new leaf behaviour (still absent, still no tombstone) rather than removed; the project rule is that tests are not deleted without approval.

## Migration Plan

1. One additive migration: nullable `comments.edited_at`. `down()` drops it. Existing rows read as never edited, which is true.
2. Run it against both databases — `php artisan migrate` and `DB_DATABASE=wink php artisan migrate`.
3. Deploy is the normal process and is the developer's to run. No queue-worker restart is needed for correctness (no queued code changes), though the standing rule to restart after every deploy still applies.
4. Rollback: revert the code, then roll back the migration. Comments soft-deleted in the meantime stay soft-deleted and, under the old code, simply go back to being unloaded — along with their replies, as before.
