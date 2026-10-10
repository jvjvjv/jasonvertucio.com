## Context

See `proposal.md` for motivation. What shapes the approach:

- Moderation is three routes in `routes/admin.php`, in a group of their own behind `['auth', 'can:manage-blog', HandleInertiaRequests::class]`. `CommentModerationController::markSpam()`/`markNotSpam()` each inline the state transition (`is_spam`/`approved_at`) and return `back()` with a `success` flash, which the admin layout's snackbar renders.
- Laravel's `can:` middleware takes one ability. There is no "either of these" form.
- Keystone registers a `Gate::before` that returns `true` for a super-admin, answers **definitively** for any ability whose name matches a Keystone permission row (one uncached lookup per check), and otherwise returns `null` so ordinary gates and policies run. `hasPermissionTo('name')` compares against the user's already-loaded permission and role collections and does not throw for an unknown name.
- `AdminNavigationService::canAccess()` filters each `navigation.json` item with `$user->can($item['can'])` — a single ability string, resolved through the Gate, so it works for a defined gate as well as for a permission name.
- The app has no `Gate::define` and no `app/Policies` today. `AppServiceProvider::boot()` is where the `comments` rate limiter and the comment observer are registered.
- The public thread is plain Blade with no page JavaScript. `CommentThread::tree()` loads the post's comments in one query (asserted by `CommentDisplayTest::test_the_thread_costs_one_comment_query`, which counts queries against `comments` only); `CommentNode` renders one node and recurses. The reply form is a native `<details>`.
- `routes/blog.php` holds `POST /blog/{slug}/comments` (`comments.store`) above the `GET /blog/{slug}` wildcard.
- Permissions are added to existing environments by migration (`2026_03_11_223744_add_ai_tools_permission.php` is the precedent) and to fresh ones by `AuthKitSeeder`, whose `super-admin` role receives `Permission::all()`. The `permissions` table has nullable `title` and `description` columns, shown in Roles & Permissions.
- `edit-and-delete-comments` is drafted but unimplemented and edits the same thread files. Its decisions 1 and 8 (resolve the viewer's status once per thread and pass it down; controls as native `<details>` disclosures) are followed here so the two changes compose instead of competing.

## Goals / Non-Goals

**Goals:**
- One definition of "may moderate comments", used by the admin routes, the post-page route, the navigation and the thread's control, so they cannot disagree.
- One definition of what marking spam does to a row, used by both entry points.
- No new JavaScript on the public blog, and no per-comment authorization query.

**Non-Goals:**
- Showing spam comments, or a restore control, on the public post page.
- A policy class. There is one rule and it does not depend on the comment.
- Granting the permission to any role, or adding a public-site navigation link to the queue.
- Changing how `edit-and-delete-comments` or `unify-ip-ban-triggers` define their own gates.

## Decisions

### 1. A derived gate, `moderate-comments`, is the single rule

```php
Gate::define('moderate-comments', fn (User $user): bool =>
    $user->hasPermissionTo('manage-comments') || $user->hasPermissionTo('manage-blog'));
```

Registered in `AppServiceProvider::boot()`. Every consumer asks for the gate, never for either permission directly: `can:moderate-comments` on the route groups, `"can": "moderate-comments"` in `navigation.json`, and `$user->can('moderate-comments')` in the thread. Super-admins pass through Keystone's `before` bypass without holding either permission, which is what the rest of the app already does.

The ability name is deliberately **not** a permission name: `manage-comments` is what a role is granted, `moderate-comments` is the question the code asks.

*Alternative rejected — two route groups, or a custom middleware taking a list:* the first duplicates the routes; the second solves the routes but leaves the navigation and the thread to re-implement "either" on their own.

*Alternative rejected — make `manage-blog` imply `manage-comments` by granting it to every role that holds `manage-blog`:* that is the "replace" option the developer declined. It also drifts — a role given `manage-blog` later would not moderate.

*Alternative rejected — a `CommentPolicy`:* `edit-and-delete-comments` introduces one for its ownership rule. Moderation does not depend on which comment it is, so a gate is the smaller tool; if that change lands, nothing here needs to move.

### 2. The state transition moves onto the model

`Comment::markAsSpam()` and `Comment::markAsNotSpam()` hold the two `update()` calls that are inline in `CommentModerationController` today. The queue's two actions and the new post-page action call them. This is the only refactor in the change and it exists because there are now two callers of the spam transition and the "never simultaneously approved and spam" invariant should have one writer per direction.

Both stay `update()` calls with the same attributes, so behaviour — including `updated_at` moving — is unchanged.

### 3. The post page gets its own route

```
POST /blog/{slug}/comments/{comment}/spam   comments.spam
```

In `routes/blog.php` beside `comments.store`, with `['auth', 'can:moderate-comments']`, handled by `CommentController::markSpam()`. `auth` first, so a guest is redirected to login rather than shown a 403. `{comment}` uses implicit binding. The action 404s when the comment's post does not have the slug in the URL, then calls `markAsSpam()` and redirects to `route('post', $slug).'#comments'` flashing `comment_marked_spam`. It redirects to `#comments`, not the comment's own anchor, because a spam leaf no longer has one.

`comment-thread.blade.php` renders the flash in the same banner style as `comment_posted`.

*Why not post to the existing `/admin/comments/{comment}/spam`:* it returns `back()`, which leans on the Referer header to find the post, and flashes `success`, which only the admin layout displays. A dedicated route knows where it is going and says so on the page it lands on. This is the same split `edit-and-delete-comments` makes for delete.

No throttle: the route is authenticated and permission-gated, and the `comments` limiter is the posting allowance.

Marking an already-spam comment is not an error — the transition is idempotent and the redirect and flash are the same.

### 4. The thread resolves the viewer's moderation right once

`CommentThread` computes `auth()->user()?->can('moderate-comments') ?? false` once and passes it to each `CommentNode` as a boolean (`canModerate`), including through the recursive `<x-comment-node>` call in `comment-node.blade.php`. The node template shows the control when `$canModerate && $comment->isVisible()`.

*Why not `@can('moderate-comments')` per node:* every Gate check runs Keystone's `before` lookup; a 50-comment thread would add 50 queries to a page that costs one. A guest costs zero. The existing one-query test counts `comments` queries only and keeps passing either way, so a test for this change asserts the total query count does not grow with the number of comments.

If `edit-and-delete-comments` lands first, its node already receives a viewer and an admin flag; `canModerate` is one more boolean alongside them, not a replacement.

### 5. The control is a native disclosure

Inside the visible comment's `<article>`, beside the Reply `<details>`: a `<details>` whose summary reads "Mark as spam" and whose body is a one-line note ("This hides the comment from the post.") and a submit button in a `POST` form with `@csrf`. Opening the disclosure is the deliberate second step; there is no `confirm()` and no script. Styling follows the existing node — utilities already in use, no gradients, no shadows, a `focus-visible` outline as on the reply form.

The queue page needs no change: it already offers both actions to whoever can reach it.

### 6. The permission is created by a migration and assigned by hand

A data-only migration following `add_ai_tools_permission`: forget the cached permissions, `firstOrCreate` `manage-comments` on the `web` guard with a `title` ("Manage comments") and a `description` saying it covers the moderation queue and marking spam, and nothing in the blog admin. `down()` deletes the row. It assigns the permission to no role.

`AuthKitSeeder` gains `manage-comments` in its permission list under the blog heading. It is not added to the `admin` or `editor` role lists — both hold `manage-blog`, which already moderates. (The seeder's `super-admin` receives every permission, as it does for all of them.)

*Why not grant it to `admin` as the precedent migration does:* that precedent introduced a permission nothing else covered. Here every existing moderator already qualifies, and auto-granting would blur the one thing the new permission is for — being the narrow grant.

## Risks / Trade-offs

- **A permission row named `moderate-comments` would hijack the gate**, because Keystone's `before` answers definitively for any ability matching a permission name, and Roles & Permissions lets an admin create arbitrary names → A feature test asserts a user holding only `manage-comments` passes `can('moderate-comments')`; creating such a row would fail it. The name is also called out in `CLAUDE.md`.
- **A `manage-comments`-only user has no way into the admin from the public site** — the site navigation's Canvas link is `manage-blog`, and `/admin` itself needs `manage-unauthenticated-viewers` → They moderate from the post page, and reach the queue by URL, where the admin app bar lists Comments. A site-navigation link is a site-settings edit the developer can make.
- **The queue shows commenter email and IP address** to anyone who can moderate → Unchanged in kind — `manage-blog` holders see it today — but `manage-comments` will be handed to people who did not have it. Accepted: the IP is there so a spam determination can feed a ban.
- **Mis-clicks on a public page** → The disclosure makes it two deliberate actions, and the transition is reversible from the queue.
- **Overlap with `edit-and-delete-comments`** in three files → Both changes follow the same shape (flag resolved once, `<details>` controls), so the merge is additive. Whichever is applied second must re-run both display test sets.
- **Keystone gates are off in the console** → Verify authorization with feature tests, not tinker.

## Migration Plan

1. One data-only migration creating the permission. Run it against both databases — `php artisan migrate` and `DB_DATABASE=wink php artisan migrate`.
2. Deploy is the normal process and is the developer's to run. No queued code changes; the standing rule to restart the worker after every deploy still applies.
3. After deploy, grant `manage-comments` to whichever role should have it, in Roles & Permissions. Until then nothing changes for anyone: existing moderators keep moderating through `manage-blog`, and gain the post-page control.
4. Rollback: revert the code, then roll back the migration (which deletes the permission row and with it any role grants). Comments marked spam in the meantime stay spam.
