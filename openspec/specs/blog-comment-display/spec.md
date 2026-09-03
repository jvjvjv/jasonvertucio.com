# blog-comment-display

## Purpose

Defines how a post's comment tree is queried, ordered, indented and tombstoned for public display, and establishes that the Facebook comments widget is gone.

## Requirements

### Requirement: The Facebook comments widget is removed
The blog SHALL NOT load the Facebook JavaScript SDK, render a `fb-comments` element, or emit an `fb:app_id` meta tag. The Facebook webhook route and its controller SHALL be removed.

#### Scenario: No Facebook assets are requested
- **WHEN** any page of the site is rendered
- **THEN** the response contains no reference to `connect.facebook.net`, no `#fb-root` element, and no `fb-comments` element

#### Scenario: The webhook path no longer resolves
- **WHEN** a request is made to `/mlopnadjs22tn`
- **THEN** it returns 404

### Requirement: A post's comment thread renders from the comments table
A blog post page SHALL render its comment tree from the `comments` table. The query SHALL load the full thread for the post in a single query and assemble the tree in application code. A comment SHALL be included only when `approved_at` is non-null, `is_spam` is false, and it is not soft-deleted.

#### Scenario: Approved comments appear
- **WHEN** a post has comments with non-null `approved_at` and `is_spam` false
- **THEN** each is rendered in the thread, ordered chronologically within its sibling group

#### Scenario: Soft-deleted comments do not appear
- **WHEN** a comment has a non-null `deleted_at`
- **THEN** it is absent from the rendered thread entirely, with no tombstone

#### Scenario: A post with no comments
- **WHEN** a post has no comment rows
- **THEN** the page renders an empty state rather than an error or a bare heading

#### Scenario: The thread costs one query
- **WHEN** a post with nested comments at several depths is rendered
- **THEN** the comments are fetched in a single query rather than one per nesting level

### Requirement: A hidden comment renders as a tombstone and its replies survive
When a comment is excluded by the spam predicate but is not soft-deleted, the thread SHALL render a placeholder in its position and SHALL continue to render its descendants at their original depths. Descendants SHALL NOT be hidden, re-parented, or have their `depth` altered as a consequence.

#### Scenario: A spam comment mid-thread
- **WHEN** a depth-1 comment is marked spam and has an approved depth-2 reply which itself has an approved depth-3 reply
- **THEN** the depth-1 position renders a removal placeholder, and the depth-2 and depth-3 comments still render at depths 2 and 3

#### Scenario: The tombstone reveals no content
- **WHEN** a tombstone is rendered
- **THEN** it exposes neither the comment body nor the commenter's name or email

#### Scenario: A spam leaf comment
- **WHEN** a comment with no replies is marked spam
- **THEN** no tombstone is rendered for it

### Requirement: Visual indentation is capped at two levels
The rendered thread SHALL offset comments by at most two levels of indentation regardless of their true `depth`. A comment whose `depth` exceeds 2 SHALL display an explicit reference to the comment it replies to. True `depth` SHALL remain unchanged in the data and SHALL continue to govern reply eligibility.

#### Scenario: A depth-4 comment is indented like a depth-2 comment
- **WHEN** a comment with `depth` 4 is rendered
- **THEN** its visual offset equals that of a depth-2 comment, and it displays a reference naming the author of its parent

#### Scenario: Depth still governs the reply control
- **WHEN** a comment with `depth` 5 is rendered
- **THEN** no reply control is offered on it, even though its indentation matches shallower comments
