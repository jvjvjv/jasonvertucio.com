## ADDED Requirements

### Requirement: A mark-as-spam control is offered only to moderators
The public thread SHALL show a mark-as-spam control on each displayed comment when, and only when, the viewer may moderate comments. The control SHALL require a deliberate second step before it submits, and SHALL work without page JavaScript. A removal placeholder SHALL NOT carry the control. Offering the control SHALL NOT change the number of queries used to load the thread's comments.

#### Scenario: A moderator views a thread
- **WHEN** a user who may moderate views a post with displayed comments from registered and anonymous commenters
- **THEN** every displayed comment carries a mark-as-spam control

#### Scenario: A logged-in user who is not a moderator
- **WHEN** a logged-in user who may not moderate views a post with comments, including comments they wrote
- **THEN** no comment carries a mark-as-spam control

#### Scenario: A guest views a thread
- **WHEN** a visitor who is not logged in views a post with comments
- **THEN** no comment carries a mark-as-spam control

#### Scenario: A tombstone has no control
- **WHEN** a moderator views a thread containing a removal placeholder
- **THEN** the placeholder carries no mark-as-spam control

#### Scenario: The control does not fire on a single click
- **WHEN** a moderator activates the control on a comment
- **THEN** a confirming action is revealed, and the comment is marked only when that confirming action is used

#### Scenario: The confirmation is shown after marking
- **WHEN** a moderator has just marked a comment as spam from the post page
- **THEN** the post's comment section shows a confirmation, and the comment is no longer displayed

#### Scenario: The thread still costs one comment query
- **WHEN** a post with nested comments is rendered for a moderator
- **THEN** the comments are fetched in a single query, with no additional query per comment to decide whether to show the control
