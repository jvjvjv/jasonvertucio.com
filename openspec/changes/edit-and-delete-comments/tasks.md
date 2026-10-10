## 1. Schema and model

- [ ] 1.1 Create a migration adding nullable `edited_at` timestamp to `comments` after `approved_at`, with a `down()` that drops it; run `php artisan migrate` and `DB_DATABASE=wink php artisan migrate` and verify the column exists in both databases
- [ ] 1.2 On `Comment`: cast `edited_at` to `datetime` (leave it out of `$fillable`), add `isEdited()`, and make `isVisible()` also require `! $this->trashed()`; verify with unit assertions in `tests/Feature/CommentTest.php` that a trashed approved comment is not visible and a comment with `edited_at` set reports edited
- [ ] 1.3 Add `Comment::isManageableBy(?User $user, bool $isAdmin): bool` per design decision 1; verify with a data-driven test covering owner/visible, owner/spam, other user, anonymous comment with matching email, null user, and admin for each
- [ ] 1.4 Add an `edited()` state to `CommentFactory` if tests need it, following the existing `anonymous()`/`asReply()` states; verify the factory still produces valid rows by running `tests/Feature/CommentTest.php`

## 2. Authorization

- [ ] 2.1 Create `app/Policies/CommentPolicy.php` (`php artisan make:policy CommentPolicy --model=Comment --no-interaction`, trimmed to `update` and `delete`), each delegating to `isManageableBy()` with `$user->can('manage-blog')`; verify auto-discovery with a feature test asserting `$owner->can('update', $comment)` is true and `$stranger->can('update', $comment)` is false
- [ ] 2.2 Create `app/Http/Requests/UpdateCommentRequest.php`: `authorize()` via the policy, `message` rules and messages matching `StoreCommentRequest`, error bag `commentEdit`, and `getRedirectUrl()` appending `#comment-{id}`; verified by the endpoint tests in 3.4

## 3. Public edit and delete endpoints

- [ ] 3.1 Register `PUT` and `DELETE /blog/{slug}/comments/{comment}` (`comments.update`, `comments.destroy`) in `routes/blog.php` behind `auth`; verify with `php artisan route:list --path=blog` that both resolve to `CommentController` and `GET /blog/{slug}` is unaffected
- [ ] 3.2 Implement `CommentController::update()`: 404 when the comment's post slug differs from the route slug, change only `message`, set `edited_at` only when the message is dirty, redirect to the post at `#comment-{id}` with `comment_updated`; verified in 3.4
- [ ] 3.3 Implement `CommentController::destroy()`: same slug check, authorize `delete`, soft delete, redirect to the post at `#comments` with `comment_deleted`; verified in 3.4
- [ ] 3.4 Create `tests/Feature/CommentEditingTest.php` (`php artisan make:test --phpunit CommentEditingTest`, `DatabaseTransactions`, `Mail::fake()`) covering every scenario in `specs/blog-comment-editing/spec.md`: owner edit and delete, empty and over-long edits, owner blocked on a spam comment, 403 for another user's and for an email-matching anonymous comment, guest redirected to login for both verbs, admin edit/delete of another user's, anonymous and spam comments, extra fields ignored, authorship unchanged after an admin edit, `edited_at` set on change and not on an identical resubmit or a spam/restore cycle, wrong-slug 404, already-deleted 404, row retained with `deleted_at`, replies' `parent_id`/`depth` untouched, and no mail sent on edit or delete; verify with `php artisan test --compact tests/Feature/CommentEditingTest.php`

## 4. Thread display

- [ ] 4.1 In `CommentThread`: add `withTrashed()` to the tree query, resolve the viewer and `can('manage-blog')` once, and expose both to the template; pass them through `comment-thread.blade.php` to each `<x-comment-node>` (including the recursive call in `comment-node.blade.php`); verify the existing one-query test still passes
- [ ] 4.2 In `CommentNode`: accept the viewer and admin flag, add `canManage()` delegating to `isManageableBy()`, and add an `isEditTarget()` helper keyed on `old('editing')`; verified in 4.6
- [ ] 4.3 In `comment-node.blade.php`: add the "(edited)" marker after the timestamp, and the Edit and Delete `<details>` controls per design decision 8, shown only when `canManage()`; the edit form posts `@method('PUT')` with the hidden `editing` id and reads the `commentEdit` error bag; activate the `tailwindcss-development` skill and keep to existing utilities with no gradients or shadows; verified in 4.6
- [ ] 4.4 In `comment-node.blade.php`: change the backlink to read "a removed comment" when the parent is not visible, still linking to its anchor; verified in 4.6
- [ ] 4.5 In `comment-thread.blade.php`: render `comment_updated` and `comment_deleted` flashes in the existing `comment_posted` banner style; verified in 4.6
- [ ] 4.6 Update `tests/Feature/CommentDisplayTest.php` for every scenario in `specs/blog-comment-display/spec.md`: rewrite `test_soft_deleted_comments_are_absent_with_no_tombstone` to assert the deleted **leaf** case (do not delete it), and add tests for a deleted comment mid-thread tombstoning with descendants at original depths, tombstones for spam and deleted being identical markup, no controls on a tombstone, a hidden comment whose only replies are hidden rendering nothing, deleted comments excluded from the count, a post whose only comment was deleted showing the empty state, the edited marker present/absent/absent-after-restore, controls for owner vs other vs guest vs admin, the edit field pre-filled, a rejected edit reported only on its own comment, and the backlink not naming a deleted or a spam parent; verify with `php artisan test --compact tests/Feature/CommentDisplayTest.php`

## 5. Submission guard

- [ ] 5.1 In `StoreCommentRequest`: replace `exists:comments,id` with `Rule::exists('comments', 'id')->whereNull('deleted_at')` and add its message; add tests to `tests/Feature/CommentSubmissionTest.php` asserting a reply to a soft-deleted comment gets a `parent_id` validation error, creates no row (in particular no top-level row), and returns the submitted text via `old()`; verify with `php artisan test --compact tests/Feature/CommentSubmissionTest.php`

## 6. Moderation queue

- [ ] 6.1 Add `DELETE /admin/comments/{comment}` (`admin.comments.destroy`) to the existing `manage-blog` group in `routes/admin.php` and `CommentModerationController::destroy()` returning `back()` with a success flash; verified in 6.3
- [ ] 6.2 In `resources/js/admin/pages/comments/Index.tsx`: add a Delete row action for every row using `useConfirmDialog` and `router.delete`, with `preserveScroll`; activate the `inertia-react-development` skill; fix Prettier in this file before committing; verify `npm run build` succeeds
- [ ] 6.3 Add tests to `tests/Feature/CommentModerationTest.php` for every scenario in `specs/blog-comment-moderation/spec.md`: a moderator deletes a spam comment and it leaves the queue, deleting an approved comment with replies leaves the replies listed and displayed on the post, an unpermitted user is denied and nothing is deleted, and `approved_at`/`is_spam` are unchanged on the deleted row; verify with `php artisan test --compact tests/Feature/CommentModerationTest.php`

## 7. Documentation and wrap-up

- [ ] 7.1 Update the *Comment System* section of `CLAUDE.md`: edit/delete behaviour and who may do it, `edited_at`, the tombstone rule now covering deleted comments, the new key files (`CommentPolicy`, `UpdateCommentRequest`) and the three new routes; verify the section no longer describes soft-deleted comments as tombstone-free
- [ ] 7.2 Run all five comment test files together (`php artisan test --compact --filter=Comment`) and confirm they pass, then ask the developer whether to run the full suite
- [ ] 7.3 Load a post with nested comments in the local site as an owner, another user, a guest and a `manage-blog` user, and confirm by eye that the controls, the "(edited)" marker, the delete confirmation step and a mid-thread tombstone all render as specified
