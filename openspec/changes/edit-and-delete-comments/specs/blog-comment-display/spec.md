## MODIFIED Requirements

### Requirement: A post's comment thread renders from the comments table
A blog post page SHALL render its comment tree from the `comments` table. The query SHALL load the full thread for the post in a single query and assemble the tree in application code. A comment's content SHALL be rendered only when `approved_at` is non-null, `is_spam` is false, and it is not soft-deleted.

#### Scenario: Approved comments appear
- **WHEN** a post has comments with non-null `approved_at` and `is_spam` false
- **THEN** each is rendered in the thread, ordered chronologically within its sibling group

#### Scenario: Soft-deleted comments do not appear
- **WHEN** a comment has a non-null `deleted_at`
- **THEN** neither its message nor its author's name appears anywhere in the rendered thread

#### Scenario: Soft-deleted comments are not counted
- **WHEN** a post has two visible comments and one soft-deleted comment
- **THEN** the displayed comment count is two

#### Scenario: A post with no comments
- **WHEN** a post has no comment rows
- **THEN** the page renders an empty state rather than an error or a bare heading

#### Scenario: A post whose only comment was deleted
- **WHEN** a post's only comment is soft-deleted
- **THEN** the page renders the same empty state as a post with no comments

#### Scenario: The thread costs one query
- **WHEN** a post with nested comments at several depths, some of them soft-deleted, is rendered
- **THEN** the comments are fetched in a single query rather than one per nesting level

### Requirement: A hidden comment renders as a tombstone and its replies survive
When a comment is hidden — excluded by the spam predicate, or soft-deleted — the thread SHALL render a placeholder in its position whenever it has at least one descendant that is itself displayed, and SHALL continue to render its descendants at their original depths. Descendants SHALL NOT be hidden, re-parented, or have their `depth` altered as a consequence. A hidden comment with no displayed descendant SHALL render nothing. The placeholder SHALL be the same for a spam comment and a deleted one.

#### Scenario: A spam comment mid-thread
- **WHEN** a depth-1 comment is marked spam and has an approved depth-2 reply which itself has an approved depth-3 reply
- **THEN** the depth-1 position renders a removal placeholder, and the depth-2 and depth-3 comments still render at depths 2 and 3

#### Scenario: A deleted comment mid-thread
- **WHEN** a depth-1 comment is soft-deleted and has an approved depth-2 reply which itself has an approved depth-3 reply
- **THEN** the depth-1 position renders a removal placeholder, and the depth-2 and depth-3 comments still render at depths 2 and 3

#### Scenario: The tombstone reveals no content
- **WHEN** a tombstone is rendered
- **THEN** it exposes neither the comment body nor the commenter's name or email

#### Scenario: The tombstone does not say why
- **WHEN** a spam comment and a deleted comment are both rendered as tombstones
- **THEN** the two placeholders are indistinguishable

#### Scenario: A tombstone offers no controls
- **WHEN** a tombstone is rendered for any viewer
- **THEN** it offers no reply, edit or delete control

#### Scenario: A spam leaf comment
- **WHEN** a comment with no replies is marked spam
- **THEN** no tombstone is rendered for it

#### Scenario: A deleted leaf comment
- **WHEN** a comment with no replies is soft-deleted
- **THEN** no tombstone is rendered for it

#### Scenario: A hidden comment whose replies are all hidden
- **WHEN** a soft-deleted comment's only reply is itself soft-deleted or marked spam and has no displayed descendants
- **THEN** no tombstone is rendered for either

## ADDED Requirements

### Requirement: An edited comment is marked as edited
A displayed comment whose message has been edited SHALL carry a visible "edited" marker. A comment that has never been edited SHALL NOT. The marker SHALL NOT identify who made the edit.

#### Scenario: An edited comment shows the marker
- **WHEN** a comment whose message has been edited is rendered
- **THEN** an "edited" marker appears with it

#### Scenario: An unedited comment shows no marker
- **WHEN** a comment that has never been edited is rendered
- **THEN** no "edited" marker appears with it

#### Scenario: A restored comment shows no marker
- **WHEN** a never-edited comment that was marked spam and then restored is rendered
- **THEN** no "edited" marker appears with it

### Requirement: Edit and delete controls are offered only to those who may use them
The rendered thread SHALL offer edit and delete controls on a displayed comment only to a viewer permitted to perform that action on that comment. The controls SHALL work without client-side scripting, and the delete control SHALL require a second, explicit confirmation step before the comment is deleted.

#### Scenario: An author sees controls on their own comment only
- **WHEN** a logged-in non-admin user views a post carrying one comment they wrote and one written by someone else
- **THEN** edit and delete controls are offered on their own comment and not on the other

#### Scenario: A guest sees no controls
- **WHEN** a visitor who is not logged in views a post with comments, including anonymous ones
- **THEN** no edit or delete control is offered on any comment

#### Scenario: An admin sees controls on every displayed comment
- **WHEN** a user holding `manage-blog` views a post with comments from registered and anonymous commenters
- **THEN** edit and delete controls are offered on each displayed comment

#### Scenario: The edit control starts from the current text
- **WHEN** a permitted viewer opens the edit control on a comment
- **THEN** the field is pre-filled with the comment's current message

#### Scenario: A rejected edit is reported on the comment it was aimed at
- **WHEN** an edit is rejected with a validation error
- **THEN** the error and the rejected text are shown on that comment's edit control, and on no other comment's

### Requirement: A backlink to a removed comment does not name its author
The "in reply to" reference shown on a comment whose `depth` exceeds 2 SHALL NOT expose the name of a parent that is hidden. It SHALL still link to the parent's position in the thread.

#### Scenario: A deep reply to a deleted comment
- **WHEN** a depth-4 comment is rendered whose parent has been soft-deleted
- **THEN** its reference links to the parent's tombstone and does not contain the parent author's name

#### Scenario: A deep reply to a spam comment
- **WHEN** a depth-4 comment is rendered whose parent has been marked spam
- **THEN** its reference links to the parent's tombstone and does not contain the parent author's name
