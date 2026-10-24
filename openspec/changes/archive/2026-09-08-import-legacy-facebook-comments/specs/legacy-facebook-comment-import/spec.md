## Purpose

Recovers blog comments left through the retired Facebook Comments Plugin, which stored
comment content only on Facebook's servers, by pulling them from the Facebook Graph API
and materializing them as native `Comment` rows so historical discussion is visible on
the site again.

## ADDED Requirements

### Requirement: Import command fetches comments per post from the Graph API
The system SHALL provide an Artisan command that, for each blog post, queries the
Facebook Graph API's comments edge for that post's canonical URL and imports every
comment returned into the `comments` table.

#### Scenario: Post has legacy Facebook comments
- **WHEN** the import command runs for a post whose URL has comments on the Facebook
  Graph API
- **THEN** each returned comment is created as a `Comment` row associated with that
  post

#### Scenario: Post has no legacy Facebook comments
- **WHEN** the import command runs for a post whose URL has no comments on the Facebook
  Graph API
- **THEN** no `Comment` rows are created for that post and the command continues to the
  next post without error

#### Scenario: Facebook Graph API is unreachable or errors for a post
- **WHEN** the Graph API request for a given post's comments fails (network error, rate
  limit, or the post's URL is no longer recognized by Facebook)
- **THEN** the command records the failure for that post, skips it, and continues
  importing the remaining posts rather than aborting the whole run

### Requirement: Imported comments preserve original authorship, content, and time
Each imported comment SHALL retain the commenter's Facebook display name, the original
message text, and the original Facebook comment timestamp, so it appears in its true
chronological position in the thread rather than as a new comment.

#### Scenario: Comment is imported
- **WHEN** a Facebook comment is imported
- **THEN** the resulting `Comment` row's name reflects the Facebook commenter's display
  name at the time of import, its message matches the Facebook comment text, and its
  creation time matches the Facebook comment's original posted time

### Requirement: Imported comment threading matches the original Facebook thread
An imported comment that was a reply to another Facebook comment SHALL be linked as a
reply to that comment's imported counterpart, with its nesting depth computed the same
way native replies are, capped at the system's maximum thread depth.

#### Scenario: Reply's parent was also imported
- **WHEN** a Facebook comment that replied to another Facebook comment is imported and
  the parent comment was already imported
- **THEN** the imported reply is linked to the imported parent and its depth is one
  greater than the parent's depth

#### Scenario: Reply's parent cannot be resolved
- **WHEN** a Facebook comment's parent comment was deleted on Facebook, was not
  returned by the API, or importing it as a reply would exceed the system's maximum
  thread depth
- **THEN** the comment is imported as a top-level comment rather than being dropped

### Requirement: Imported comments are visible without re-review
An imported comment SHALL be immediately visible under the same visibility rule as any
other approved comment, without requiring separate moderator action, since it was
already publicly visible on Facebook for the duration it was hosted there.

#### Scenario: Comment finishes importing
- **WHEN** a Facebook comment import completes for a comment
- **THEN** that comment is immediately visible on the post's comment thread

### Requirement: Re-running the import does not duplicate comments
The system SHALL identify each imported comment by its originating Facebook comment id
and skip any comment already imported, so the command can be re-run safely (e.g. to
pick up new legacy comments discovered later, or after fixing a per-post failure)
without creating duplicate rows.

#### Scenario: Import command runs a second time
- **WHEN** the import command is run again after a previous successful run for the same
  post
- **THEN** comments already imported are not recreated, and only comments not
  previously imported are added
