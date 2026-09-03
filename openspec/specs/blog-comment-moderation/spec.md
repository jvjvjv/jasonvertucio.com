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
A moderation page SHALL exist at `/admin/comments`, rendered through Inertia consistently with the rest of the admin panel, and gated by the same authentication and permission group as the other routes in `routes/admin.php`. It SHALL be reachable from the admin navigation. It SHALL NOT be placed under the `/canvas` prefix, which Canvas's catch-all route claims in full.

#### Scenario: A permitted user opens the queue
- **WHEN** a user holding the required permission visits `/admin/comments`
- **THEN** the comment list renders with each comment's post, author name, body, submission time, and current state

#### Scenario: An unpermitted user is refused
- **WHEN** a user without the required permission visits `/admin/comments`
- **THEN** access is denied

#### Scenario: Both actions are offered per comment
- **WHEN** the queue lists an approved comment and a spam comment
- **THEN** the approved one offers a mark-as-spam action and the spam one offers a not-spam action

#### Scenario: The route is not shadowed
- **WHEN** the application's routes are resolved
- **THEN** `/admin/comments` reaches the moderation controller and is not intercepted by Canvas's `{view?}` catch-all
