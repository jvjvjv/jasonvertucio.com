## Why

Comment moderation is gated on `manage-blog`, the permission that also opens the whole Canvas blog admin. There is no way to let someone clear spam out of the comments without also handing them the ability to write and publish posts. Moderation also only happens in the queue at `/admin/comments`, so a moderator reading a post who spots spam has to leave the page, find the comment again in a paginated list, and act on it there.

## What Changes

- A new Keystone permission, **`manage-comments`**, that grants comment moderation and nothing else.
- Comment moderation — viewing the queue, marking spam, marking not-spam — is allowed to a user holding **either** `manage-comments` or `manage-blog`. `manage-blog` keeps working exactly as it does today; nobody loses access.
- The **Comments** item in the admin navigation is shown to holders of either permission.
- A moderator sees a **Mark as spam** control on each visible comment on the public post page. Using it has the same effect as the queue's action and returns them to the post.
- The permission is created by a migration. It is **not** assigned to any role automatically — existing moderators already qualify through `manage-blog`, and the point of the new permission is to grant it deliberately, from Roles & Permissions.

Decisions confirmed with the developer before drafting: either permission moderates (not a replacement); the control appears in both the queue and the post page; `manage-comments` covers all moderation, not only the spam direction.

Out of scope:
- Restoring a comment (not-spam) from the post page. A spam comment is hidden or tombstoned there and a tombstone reveals nothing, so there is nothing to offer a control on; restoring stays in the queue.
- Removing moderation from `manage-blog`.
- Whether `manage-comments` should also grant the edit/delete powers proposed in `edit-and-delete-comments`, or the ban-IP action proposed in `unify-ip-ban-triggers`. Both of those unimplemented changes name `manage-blog`; see Impact.
- A link to the queue from the public site navigation (that list is site-settings data, edited in the admin).

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `blog-comment-moderation`: who may moderate changes from one permission to either of two; the `manage-comments` permission is defined, including what it does not grant; marking spam becomes possible from the post page.
- `blog-comment-display`: the public thread offers a mark-as-spam control, to moderators only.

## Impact

- **Schema/data**: one migration creating the `manage-comments` permission row (no table changes). It must be run against both `jasonvertucio` and `wink`.
- **Routes**: the three existing `/admin/comments` routes change gate; one new route, `POST /blog/{slug}/comments/{comment}/spam`.
- **Code**: `AppServiceProvider` (a gate), `routes/admin.php`, `routes/blog.php`, `Comment` model, `CommentController`, `CommentModerationController`, `CommentThread`/`CommentNode` and their Blade templates, `resources/js/admin/navigation.json`, `database/seeders/AuthKitSeeder.php`.
- **Tests**: `CommentModerationTest`, `CommentDisplayTest`; the navigation test if it enumerates `can` values.
- **Docs**: the *Comment System* section of `CLAUDE.md`.
- **Other in-flight changes** (neither is implemented; this change does not edit them):
  - `edit-and-delete-comments` touches the same thread component, node component and node template, and resolves "is the viewer an admin" once per thread as `can('manage-blog')`. Whichever lands second has to merge the two sets of controls, and that change should decide whether a `manage-comments` holder counts as its "admin".
  - `unify-ip-ban-triggers` adds a ban-IP route "under the existing `manage-blog`-gated group". After this change that group is gated on the combined rule, so its wording needs a decision when it is picked up.
- No new dependencies. No notification is sent when a comment is marked spam, as today.
