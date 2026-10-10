## Purpose

Defines who may edit or delete a blog comment after it is posted, what an edit is allowed to change, and what deleting a comment does to its row and to the comments around it.

## ADDED Requirements

### Requirement: A registered commenter may edit their own comment
A logged-in user SHALL be able to change the message of a comment whose `user_id` is their own, provided that comment is publicly visible. The edited message SHALL satisfy the same content rules as a new comment's message. After a successful edit the user SHALL be returned to that comment on its post.

#### Scenario: An author edits their comment
- **WHEN** a logged-in user submits a new message for a visible comment they wrote
- **THEN** the comment's message is replaced and the post page shows the new text

#### Scenario: An empty edit is refused
- **WHEN** a logged-in user submits an empty message for their own comment
- **THEN** the edit is rejected with a validation error and the stored message is unchanged

#### Scenario: An over-long edit is refused
- **WHEN** a logged-in user submits a message longer than 5,000 characters for their own comment
- **THEN** the edit is rejected with a validation error and the stored message is unchanged

#### Scenario: An author cannot edit their hidden comment
- **WHEN** a logged-in non-admin user attempts to edit a comment they wrote that has been marked as spam
- **THEN** the attempt is refused and the stored message is unchanged

### Requirement: A registered commenter may delete their own comment
A logged-in user SHALL be able to delete a comment whose `user_id` is their own, provided that comment is publicly visible. After a successful delete the user SHALL be returned to the post's comment section.

#### Scenario: An author deletes their comment
- **WHEN** a logged-in user deletes a visible comment they wrote
- **THEN** the comment's content no longer appears on the post page

#### Scenario: An author cannot delete their hidden comment
- **WHEN** a logged-in non-admin user attempts to delete a comment they wrote that has been marked as spam
- **THEN** the attempt is refused and the comment is not deleted

### Requirement: A non-admin cannot edit or delete anyone else's comment
A logged-in user who does not hold the `manage-blog` permission SHALL NOT be able to edit or delete a comment whose `user_id` is not their own. A comment with no `user_id` belongs to no user for this purpose, regardless of the name or email address recorded on it.

#### Scenario: Editing another user's comment is refused
- **WHEN** a logged-in non-admin user attempts to edit a comment written by a different registered user
- **THEN** the response is 403 and the stored message is unchanged

#### Scenario: Deleting another user's comment is refused
- **WHEN** a logged-in non-admin user attempts to delete a comment written by a different registered user
- **THEN** the response is 403 and the comment is not deleted

#### Scenario: A matching email address confers nothing
- **WHEN** a logged-in non-admin user attempts to edit or delete an anonymous comment whose recorded email equals their account's email
- **THEN** the response is 403 and the comment is unchanged

### Requirement: A guest can neither edit nor delete any comment
A visitor who is not logged in SHALL NOT be able to edit or delete any comment, including one they submitted anonymously. No mechanism — token, cookie, email link or matching address — SHALL let an anonymous commenter reclaim a comment.

#### Scenario: A guest attempts to delete a comment
- **WHEN** a request to delete a comment arrives with no authenticated user
- **THEN** the comment is not deleted and the visitor is sent to the login page

#### Scenario: A guest attempts to edit a comment
- **WHEN** a request to edit a comment arrives with no authenticated user
- **THEN** the stored message is unchanged and the visitor is sent to the login page

### Requirement: An admin may edit or delete any comment
A user holding the `manage-blog` permission SHALL be able to edit the message of, and delete, any comment — one written by another registered user, one written anonymously, and one that is not publicly visible.

#### Scenario: An admin edits another user's comment
- **WHEN** a user holding `manage-blog` submits a new message for a comment written by a different registered user
- **THEN** the comment's message is replaced

#### Scenario: An admin deletes an anonymous comment
- **WHEN** a user holding `manage-blog` deletes a comment that has no `user_id`
- **THEN** the comment is deleted

#### Scenario: An admin deletes a spam comment
- **WHEN** a user holding `manage-blog` deletes a comment that is marked as spam
- **THEN** the comment is deleted

### Requirement: An edit changes only the message
Editing a comment SHALL change its message and nothing else about who wrote it or where it sits. The recorded `name`, `email`, `user_id`, post, parent, `depth`, approval state, spam state, `ip_address` and `user_agent` SHALL be unaffected, whoever performs the edit and whatever else the request carries.

#### Scenario: Extra fields in an edit are ignored
- **WHEN** an edit request carries a `name`, `email`, `parent_id` or `user_id` alongside the message
- **THEN** only the message changes

#### Scenario: An admin's edit does not take over authorship
- **WHEN** a user holding `manage-blog` edits another user's comment
- **THEN** the comment's `user_id` and `name` still identify the original author

### Requirement: An edit is recorded as an edit
A comment SHALL record when its message was last changed, separately from any other modification to the row. An edit that leaves the message identical SHALL NOT be recorded as an edit. Moderation actions SHALL NOT be recorded as an edit.

#### Scenario: Changing the message records the edit
- **WHEN** a comment's message is changed
- **THEN** the comment records the time of that edit

#### Scenario: Resubmitting the same message records nothing
- **WHEN** an edit is submitted whose message equals the stored message
- **THEN** the comment is not recorded as edited

#### Scenario: Marking spam is not an edit
- **WHEN** a never-edited comment is marked as spam and then restored
- **THEN** the comment is not recorded as edited

### Requirement: A comment can only be edited or deleted through its own post
An edit or delete request SHALL identify the comment together with the post it belongs to. A request naming a comment under a post it does not belong to, or naming a comment that is already deleted, SHALL be answered 404.

#### Scenario: A comment addressed through the wrong post
- **WHEN** an edit or delete request names a comment under the slug of a different post
- **THEN** the response is 404 and the comment is unchanged

#### Scenario: A deleted comment cannot be edited
- **WHEN** an edit request names a comment that has already been deleted
- **THEN** the response is 404

### Requirement: Deleting a comment retains the row and leaves its replies in place
Deleting a comment SHALL be a soft delete: the row SHALL be retained with its deletion time recorded. Deleting a comment SHALL NOT delete, hide, re-parent, or alter the `depth` of any reply to it.

#### Scenario: The row survives deletion
- **WHEN** a comment is deleted
- **THEN** its row still exists with a non-null `deleted_at`

#### Scenario: Replies are untouched
- **WHEN** a comment that has replies is deleted
- **THEN** each reply keeps its `parent_id`, its `depth` and its own visibility
