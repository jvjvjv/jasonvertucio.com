# blog-comment-submission

## Purpose

Defines who may comment on a blog post, what a submission must contain, how the three identity modes (registered, legacy Facebook, anonymous) are recorded, how nesting depth is computed and capped, and how automated and abusive submissions are resisted.

## Requirements

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
The comment form SHALL include a honeypot field that legitimate submissions leave empty, and submissions SHALL be rate limited per client address. Every comment SHALL record the submitting `ip_address` and `user_agent`, resolved through the same Cloudflare-aware logic as `IpMiddleware`, so that a later spam determination can inform an `IpBan`. The Cloudflare forwarding header SHALL be used for `ip_address` only when it holds a valid IP address; an empty or malformed header SHALL fall back to the connecting address.

#### Scenario: A honeypot submission is silently discarded
- **WHEN** a submission arrives with a non-empty honeypot field
- **THEN** no comment row is created and the response is indistinguishable from a successful submission

#### Scenario: Rapid repeat submissions are throttled
- **WHEN** the same client address exceeds the configured submission rate
- **THEN** further submissions are rejected with HTTP 429 and no comment rows are created

#### Scenario: Client address is captured behind Cloudflare
- **WHEN** a submission arrives carrying a Cloudflare forwarding header
- **THEN** `ip_address` records the originating client address rather than the proxy's

#### Scenario: A malformed forwarding header is ignored
- **WHEN** a submission arrives with a Cloudflare forwarding header that is empty or not an IP address
- **THEN** `ip_address` records the connecting address

### Requirement: A reply to a deleted comment is refused
The system SHALL reject a submission whose `parent_id` names a soft-deleted comment. It SHALL NOT create the comment at any depth — in particular it SHALL NOT fall back to posting it as a top-level comment.

#### Scenario: A reply aimed at a deleted comment
- **WHEN** a submission names a `parent_id` belonging to a soft-deleted comment
- **THEN** the submission is rejected with a validation error and no comment row is created

#### Scenario: A reply form left open across a deletion
- **WHEN** a visitor opens a reply form on a comment, that comment is then deleted, and the visitor submits the form
- **THEN** the submission is rejected with a validation error and the visitor's text is returned to them

### Requirement: A commenter's address must name its mailbox plainly
A submitted comment email address SHALL be refused unless it is a valid address that raises no RFC 5322 warnings. Addresses whose mailbox is hidden by syntax that mail systems accept but people do not write (a quoted local part, a comment in parentheses, or an ideographic full stop in the domain) SHALL be refused with a validation error, and no comment SHALL be created. Ordinary addresses, including `+tag`, dotted and internationalised-domain addresses, SHALL be accepted.

#### Scenario: A quoted local part is refused
- **WHEN** an anonymous visitor submits a comment with the email `"vic.tim"@gmail.com`
- **THEN** the submission is rejected with an email validation error and no comment row is created

#### Scenario: A comment in the address is refused
- **WHEN** an anonymous visitor submits a comment with the email `victim(c)@gmail.com`
- **THEN** the submission is rejected with an email validation error and no comment row is created

#### Scenario: Ordinary addresses are accepted
- **WHEN** an anonymous visitor submits a comment with `first.last+blog@gmail.com` or `name@bücher.de`
- **THEN** the comment is created
