## ADDED Requirements

### Requirement: Anyone may comment on a published post
The site SHALL accept comments on published blog posts from both authenticated users and anonymous visitors. An authenticated submission SHALL record `user_id`; an anonymous submission SHALL NOT. Every comment SHALL record a `name` regardless of identity mode, stored as a snapshot at posting time rather than resolved from `users` at render time.

#### Scenario: An anonymous visitor comments
- **WHEN** a visitor who is not logged in submits a name, an email address, and a message on a published post
- **THEN** a comment row is created with `user_id` null, `name` and `email` set from the submission, and `post_id` set to that post

#### Scenario: An authenticated user comments
- **WHEN** a logged-in user submits a message on a published post
- **THEN** a comment row is created with `user_id` set to their id and `name` set to a snapshot of their display name at that moment

#### Scenario: The commenter's display name survives account deletion
- **WHEN** a registered user who has commented is later deleted
- **THEN** their existing comments still render the name captured at posting time

#### Scenario: Commenting on an unpublished post is refused
- **WHEN** a submission targets a post with a null `published_at`
- **THEN** the submission is rejected and no comment row is created

### Requirement: Every comment is auto-approved on creation
A comment SHALL be created with `approved_at` set to the current time and `is_spam` set to false. There SHALL NOT be a hold-for-review step between submission and public visibility.

#### Scenario: A new comment is immediately visible
- **WHEN** any comment is successfully submitted
- **THEN** `approved_at` is non-null, `is_spam` is false, and the comment appears in the post's rendered thread on the next page load

### Requirement: Nesting is capped at a depth of 5
A comment SHALL store a denormalized `depth`, computed on insert as its parent's `depth` plus one, or zero when it has no parent. The system SHALL reject any submission whose computed depth would exceed 5.

#### Scenario: A reply to a depth-4 comment is accepted
- **WHEN** a reply is submitted to a comment whose `depth` is 4
- **THEN** the reply is created with `depth` 5

#### Scenario: A reply to a depth-5 comment is refused
- **WHEN** a reply is submitted to a comment whose `depth` is 5
- **THEN** the submission is rejected with a validation error and no comment row is created

#### Scenario: A top-level comment has depth zero
- **WHEN** a comment is submitted with no `parent_id`
- **THEN** it is created with `depth` 0

#### Scenario: A reply to a comment on a different post is refused
- **WHEN** a submission names a `parent_id` whose `post_id` differs from the submission's target post
- **THEN** the submission is rejected and no comment row is created

### Requirement: Submissions are resisted with the site's existing abuse infrastructure
The comment form SHALL include a honeypot field that legitimate submissions leave empty, and submissions SHALL be rate limited per client address. Every comment SHALL record the submitting `ip_address` and `user_agent`, resolved through the same Cloudflare-aware logic as `IpMiddleware`, so that a later spam determination can inform an `IpBan`.

#### Scenario: A honeypot submission is silently discarded
- **WHEN** a submission arrives with a non-empty honeypot field
- **THEN** no comment row is created and the response is indistinguishable from a successful submission

#### Scenario: Rapid repeat submissions are throttled
- **WHEN** the same client address exceeds the configured submission rate
- **THEN** further submissions are rejected with HTTP 429 and no comment rows are created

#### Scenario: Client address is captured behind Cloudflare
- **WHEN** a submission arrives carrying a Cloudflare forwarding header
- **THEN** `ip_address` records the originating client address rather than the proxy's
