## ADDED Requirements

### Requirement: A reply to a deleted comment is refused
The system SHALL reject a submission whose `parent_id` names a soft-deleted comment. It SHALL NOT create the comment at any depth — in particular it SHALL NOT fall back to posting it as a top-level comment.

#### Scenario: A reply aimed at a deleted comment
- **WHEN** a submission names a `parent_id` belonging to a soft-deleted comment
- **THEN** the submission is rejected with a validation error and no comment row is created

#### Scenario: A reply form left open across a deletion
- **WHEN** a visitor opens a reply form on a comment, that comment is then deleted, and the visitor submits the form
- **THEN** the submission is rejected with a validation error and the visitor's text is returned to them
