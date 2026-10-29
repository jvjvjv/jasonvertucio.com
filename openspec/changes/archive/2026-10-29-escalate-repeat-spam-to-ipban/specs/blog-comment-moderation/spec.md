## MODIFIED Requirements

### Requirement: Marking a comment as spam hides it and records why
A moderation action SHALL exist that sets `is_spam` to true and clears `approved_at` to null in a single operation. `approved_at` SHALL be the column the public display query trusts; `is_spam` SHALL NOT be the sole basis for any visibility decision. Marking a comment as spam SHALL also dispatch a `CommentMarkedAsSpam` event carrying the comment, after the column update is persisted.

#### Scenario: A comment is marked spam
- **WHEN** a moderator marks an approved comment as spam
- **THEN** `is_spam` becomes true, `approved_at` becomes null, and the comment stops appearing in the public thread

#### Scenario: The row is retained
- **WHEN** a comment is marked spam
- **THEN** the row is not deleted and remains visible in the moderation queue

#### Scenario: Marking as spam dispatches an event
- **WHEN** a moderator marks a comment as spam
- **THEN** a `CommentMarkedAsSpam` event is dispatched referencing that comment, after the `is_spam`/`approved_at` update has been persisted

#### Scenario: Marking as not-spam does not dispatch the event
- **WHEN** a moderator marks a spam comment as not spam
- **THEN** no `CommentMarkedAsSpam` event is dispatched
