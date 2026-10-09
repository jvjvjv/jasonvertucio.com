## ADDED Requirements

### Requirement: The moderation queue can delete a comment
The moderation queue SHALL offer a delete action on every comment it lists, whatever that comment's approval or spam state, gated by the same permission as the queue itself. The action SHALL ask for confirmation before deleting. Deleting from the queue SHALL have the same effect as deleting from the post page: a soft delete that leaves replies in place. A deleted comment SHALL no longer be listed in the queue.

#### Scenario: A moderator deletes a spam comment from the queue
- **WHEN** a user holding the required permission confirms the delete action on a comment marked as spam
- **THEN** the comment is soft-deleted and is no longer listed in the queue

#### Scenario: A moderator deletes an approved comment from the queue
- **WHEN** a user holding the required permission confirms the delete action on an approved comment that has replies
- **THEN** the comment is soft-deleted, and its replies remain listed and remain displayed on the post

#### Scenario: An unpermitted user cannot delete through the queue
- **WHEN** a user without the required permission sends the queue's delete request for a comment
- **THEN** access is denied and the comment is not deleted

#### Scenario: Deleting keeps the approved/spam invariant
- **WHEN** a comment is deleted from the queue
- **THEN** its `approved_at` and `is_spam` values are left as they were
