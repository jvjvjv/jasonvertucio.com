## 1. Permission and gate

- [ ] 1.1 Create a data-only migration (`php artisan make:migration add_manage_comments_permission --no-interaction`) modelled on `2026_03_11_223744_add_ai_tools_permission.php`: forget cached permissions, `firstOrCreate` `manage-comments` on the `web` guard with a `title` and `description`, assign it to no role, and delete the row in `down()`; run `php artisan migrate` and `DB_DATABASE=wink php artisan migrate` and verify the row exists in both databases and that no `role_has_permissions` row references it
- [ ] 1.2 Add `manage-comments` to the permission list in `database/seeders/AuthKitSeeder.php` under the blog heading, leaving the `admin` and `editor` role lists alone; verify by reading the diff that only the list changed
- [ ] 1.3 Define the `moderate-comments` gate in `AppServiceProvider::boot()` per design decision 1; verify with a new test in `tests/Feature/CommentModerationTest.php` that `can('moderate-comments')` is true for a user holding only `manage-comments`, true for one holding only `manage-blog`, and false for one holding neither

## 2. Model

- [ ] 2.1 Add `Comment::markAsSpam()` and `Comment::markAsNotSpam()` holding the two existing `update()` calls, and make `CommentModerationController::markSpam()`/`markNotSpam()` call them; verify the existing `tests/Feature/CommentModerationTest.php` passes unchanged

## 3. Moderation queue

- [ ] 3.1 In `routes/admin.php`, change the comment group's middleware from `can:manage-blog` to `can:moderate-comments` and update the comment above it; verify with `php artisan route:list --path=admin/comments -v` that all three routes carry the new middleware
- [ ] 3.2 In `resources/js/admin/navigation.json`, change the Comments item's `can` to `moderate-comments`; verify `tests/Feature/NavigationLinkLabelTest.php` and any other navigation test still pass
- [ ] 3.3 Extend `tests/Feature/CommentModerationTest.php` for every scenario in `specs/blog-comment-moderation/spec.md` that concerns the queue and the permission: a `manage-comments`-only user opens the queue, marks spam and marks not-spam; a `manage-blog`-only user still does all three; a user holding neither gets 403 on each and the comment is unchanged; a guest is redirected on each; a `manage-comments`-only user gets 403 on a page gated on another permission (`admin.index`); and `AdminNavigationService` offers the Comments item to each of the two permission holders and not to a user holding neither; verify with `php artisan test --compact tests/Feature/CommentModerationTest.php`

## 4. Mark as spam from the post page

- [ ] 4.1 Register `POST /blog/{slug}/comments/{comment}/spam` (`comments.spam`) in `routes/blog.php` beside `comments.store`, behind `['auth', 'can:moderate-comments']`; verify with `php artisan route:list --path=blog` that it resolves to `CommentController@markSpam` and `GET /blog/{slug}` is unaffected
- [ ] 4.2 Implement `CommentController::markSpam()` per design decision 3: 404 when the comment's post slug differs from the route slug, call `markAsSpam()`, redirect to the post at `#comments` flashing `comment_marked_spam`; verified in 4.3
- [ ] 4.3 Add tests to `tests/Feature/CommentModerationTest.php` for the post-page scenarios in `specs/blog-comment-moderation/spec.md`: a `manage-comments` moderator and a `manage-blog` moderator each mark an approved comment (row retained, `is_spam` true, `approved_at` null, redirect to the post, flash set); a non-moderator is refused on their own comment; a guest is redirected to login; a wrong-slug request 404s and leaves the comment unchanged; an already-spam comment stays spam without error; a marked comment's approved replies still render under a placeholder; and no mail is sent; verify with `php artisan test --compact tests/Feature/CommentModerationTest.php`

## 5. Thread display

- [ ] 5.1 In `CommentThread`: resolve `can('moderate-comments')` once for the current viewer and expose it to the template; pass it through `comment-thread.blade.php` to each `<x-comment-node>`, including the recursive call in `comment-node.blade.php`; add the `canModerate` constructor argument to `CommentNode`; verify `test_the_thread_costs_one_comment_query` still passes
- [ ] 5.2 In `comment-node.blade.php`: add the "Mark as spam" `<details>` control per design decision 5, shown only when `$canModerate` and the comment is visible; activate the `tailwindcss-development` skill and keep to existing utilities with no gradients or shadows; verified in 5.4
- [ ] 5.3 In `comment-thread.blade.php`: render the `comment_marked_spam` flash in the existing `comment_posted` banner style; verified in 5.4
- [ ] 5.4 Add tests to `tests/Feature/CommentDisplayTest.php` for every scenario in `specs/blog-comment-display/spec.md`: the control's form action appears once per displayed comment for a moderator (both permission variants), not at all for a logged-in non-moderator viewing their own comment or for a guest, and not on a tombstone; the control is inside a `<details>`; the confirmation banner shows after marking; and the total query count for a moderator rendering a post with three comments equals the count with thirty; verify with `php artisan test --compact tests/Feature/CommentDisplayTest.php`

## 6. Documentation and wrap-up

- [ ] 6.1 Update the *Comment System* section of `CLAUDE.md`: the `manage-comments` permission, the `moderate-comments` gate and the warning that no permission may ever be given that name, the model's two transition methods, the post-page control and its route, and the changed permission note on the `/admin/comments` routes; verify the section no longer says moderation "requires `manage-blog`"
- [ ] 6.2 Run the comment test files together (`php artisan test --compact --filter=Comment`) and confirm they pass, then ask the developer whether to run the full suite
- [ ] 6.3 In the local site, grant `manage-comments` to a role in Roles & Permissions and, as a user with only that role, confirm by eye that the control renders on a post, opening it reveals the confirm button, submitting returns to the post with the banner and the comment gone, and `/admin/comments` loads with Comments in the app bar; then confirm a user holding neither permission sees no control
