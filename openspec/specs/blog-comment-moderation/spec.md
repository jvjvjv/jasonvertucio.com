# blog-comment-moderation

## Purpose

Defines the approved/spam state machine for a comment, the invariants that state machine must hold, and the admin surface that drives it.

## Requirements

### Requirement: Marking a comment as spam hides it and records why
A moderation action SHALL exist that sets `is_spam` to true and clears `approved_at` to null in a single operation. `approved_at` SHALL be the column the public display query trusts; `is_spam` SHALL NOT be the sole basis for any visibility decision.

#### Scenario: A comment is marked spam
- **WHEN** a moderator marks an approved comment as spam
- **THEN** `is_spam` becomes true, `approved_at` becomes null, and the comment stops appearing in the public thread

#### Scenario: The row is retained
- **WHEN** a comment is marked spam
- **THEN** the row is not deleted and remains visible in the moderation queue

### Requirement: Marking a comment as not spam restores it
A moderation action SHALL exist that sets `is_spam` to false and sets `approved_at` to the current time. It SHALL NOT restore any previously held `approved_at` value.

#### Scenario: A spam comment is restored
- **WHEN** a moderator marks a spam comment as not spam
- **THEN** `is_spam` becomes false, `approved_at` is set to the time of the action, and the comment reappears in the public thread

#### Scenario: Restoring is idempotent in effect
- **WHEN** a comment is marked spam and then not spam twice in succession
- **THEN** it is visible, `is_spam` is false, and `approved_at` reflects the most recent restoration

### Requirement: A comment is never simultaneously approved and spam
The system SHALL NOT produce a row where `approved_at` is non-null and `is_spam` is true.

#### Scenario: The contradictory state is unreachable
- **WHEN** any moderation action or comment creation completes
- **THEN** the resulting row is either approved with `is_spam` false, or unapproved with `approved_at` null

### Requirement: Moderation is available at /admin/comments
A moderation page SHALL exist at `/admin/comments`, rendered through Inertia consistently with the rest of the admin panel, and available to any authenticated user who may moderate comments — that is, one holding `manage-comments` or `manage-blog`. It SHALL be reachable from the admin navigation by every such user, and the navigation SHALL NOT offer it to a user who may not moderate. It SHALL NOT be placed under the `/canvas` prefix, which Canvas's catch-all route claims in full.

#### Scenario: A permitted user opens the queue
- **WHEN** a user holding the required permission visits `/admin/comments`
- **THEN** the comment list renders with each comment's post, author name, body, submission time, and current state

#### Scenario: A manage-comments holder opens the queue
- **WHEN** a user holding `manage-comments` and not `manage-blog` visits `/admin/comments`
- **THEN** the comment list renders as it does for any other moderator

#### Scenario: An unpermitted user is refused
- **WHEN** a user without the required permission visits `/admin/comments`
- **THEN** access is denied

#### Scenario: Both actions are offered per comment
- **WHEN** the queue lists an approved comment and a spam comment
- **THEN** the approved one offers a mark-as-spam action and the spam one offers a not-spam action

#### Scenario: The navigation follows the permission
- **WHEN** the admin navigation is built for a user holding only `manage-comments`, for a user holding only `manage-blog`, and for a user holding neither
- **THEN** the first two are offered the Comments item and the third is not

#### Scenario: The route is not shadowed
- **WHEN** the application's routes are resolved
- **THEN** `/admin/comments` reaches the moderation controller and is not intercepted by Canvas's `{view?}` catch-all

### Requirement: A dedicated permission grants comment moderation
A permission named `manage-comments` SHALL exist. Comment moderation — viewing the moderation queue, marking a comment as spam, and marking a comment as not spam — SHALL be permitted to a user holding `manage-comments`, and SHALL remain permitted to a user holding `manage-blog`. Holding either one SHALL be sufficient; holding both SHALL NOT be required. A user holding neither SHALL be refused every moderation action.

#### Scenario: A manage-comments holder moderates
- **WHEN** a user holding `manage-comments` and not `manage-blog` marks an approved comment as spam
- **THEN** the comment becomes spam exactly as it would for any other moderator

#### Scenario: A manage-comments holder restores
- **WHEN** a user holding `manage-comments` and not `manage-blog` marks a spam comment as not spam
- **THEN** the comment is restored exactly as it would be for any other moderator

#### Scenario: A manage-blog holder still moderates
- **WHEN** a user holding `manage-blog` and not `manage-comments` marks a comment as spam or as not spam
- **THEN** the action succeeds as it did before `manage-comments` existed

#### Scenario: A user holding neither is refused
- **WHEN** a logged-in user holding neither permission sends any moderation request
- **THEN** access is denied and the comment is unchanged

#### Scenario: A guest is refused
- **WHEN** a request with no authenticated user sends any moderation request
- **THEN** it is redirected to log in and the comment is unchanged

### Requirement: The manage-comments permission grants nothing beyond moderation
Holding `manage-comments` SHALL NOT grant access to any surface that requires a different permission. In particular it SHALL NOT grant what `manage-blog` otherwise gates.

#### Scenario: Other admin pages stay closed
- **WHEN** a user whose only permission is `manage-comments` requests an admin page gated on another permission
- **THEN** access is denied

#### Scenario: The permission exists without being handed out
- **WHEN** the change is deployed to an environment that did not have the permission
- **THEN** `manage-comments` is present and assignable to roles, and no role has been given it automatically

### Requirement: A moderator can mark a comment as spam from the post page
A moderation action SHALL be available from the public post page that marks a comment on that post as spam. It SHALL have the same effect on the comment as the queue's mark-as-spam action, SHALL be permitted to exactly the users who may moderate, and SHALL return the moderator to the same post with a confirmation that the comment was hidden. A request naming a post the comment does not belong to SHALL NOT change the comment.

#### Scenario: Marking spam from the post
- **WHEN** a moderator uses the post page's mark-as-spam action on an approved comment
- **THEN** `is_spam` becomes true, `approved_at` becomes null, the row is retained, and the moderator is returned to that post, where a confirmation is shown

#### Scenario: A commenter who is not a moderator
- **WHEN** a logged-in user who may not moderate sends the post page's mark-as-spam request, including for a comment they wrote themselves
- **THEN** access is denied and the comment is unchanged

#### Scenario: A guest
- **WHEN** a request with no authenticated user sends the post page's mark-as-spam request
- **THEN** it is redirected to log in and the comment is unchanged

#### Scenario: The comment belongs to a different post
- **WHEN** a moderator sends the request for a comment using another post's address
- **THEN** the response is not found and the comment is unchanged

#### Scenario: The comment is already spam
- **WHEN** a moderator sends the request for a comment that is already marked as spam
- **THEN** the comment remains spam with `approved_at` null, and no error is shown

#### Scenario: A spam comment with replies
- **WHEN** a moderator marks a comment that has approved replies as spam from the post page
- **THEN** the replies remain displayed at their original depths beneath a removal placeholder
