## MODIFIED Requirements

### Requirement: The site owner is not notified of their own comments
A notification SHALL NOT be dispatched when the comment's author is the site owner. The author SHALL count as the owner when their address is the owner's mailbox, compared as described in "Addresses are compared by mailbox" (`comment-reply-notifications`), so a `+tag` or differently cased spelling of the owner's address counts.

#### Scenario: The owner comments on their own post
- **WHEN** the site owner submits a comment
- **THEN** no notification job is dispatched

#### Scenario: The owner comments from a tagged address
- **WHEN** a comment is submitted with `owner+blog@example.com` and the owner's address is `owner@example.com`
- **THEN** no notification job is dispatched

#### Scenario: Someone else comments
- **WHEN** a visitor or a non-owner user submits a comment
- **THEN** a notification job is dispatched
