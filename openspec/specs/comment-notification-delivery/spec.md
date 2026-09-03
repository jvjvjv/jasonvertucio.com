# comment-notification-delivery

## Purpose

Defines who is notified when a comment is created, through what transport, when notification is suppressed, and what the notification must tolerate.

## Requirements

### Requirement: New-comment notifications are delivered off the request path
`CommentReceivedMail` SHALL be queued rather than sent inline, and the application's queue connection SHALL be a real driver rather than `sync`, so that dispatching a notification does not block the response to the commenter.

#### Scenario: The mailable is queued
- **WHEN** a comment is created
- **THEN** a job is pushed onto the queue and the HTTP response completes without waiting for the mail transport

#### Scenario: The queue connection is not sync
- **WHEN** the application's queue configuration is resolved
- **THEN** `queue.default` is a driver that defers work to the worker process, not `sync`

#### Scenario: A transport failure does not fail the submission
- **WHEN** the mail transport is unavailable at the time a comment is created
- **THEN** the comment is still created successfully and the failure is confined to the queued job

### Requirement: The notification recipient is configurable
The recipient address SHALL be read from configuration, defaulting to `me@jasonvertucio.com` when unset. The observer SHALL NOT call `env()` directly and SHALL NOT hardcode the address.

#### Scenario: The configured address is used
- **WHEN** a notification recipient is set in configuration
- **THEN** the notification is addressed to it

#### Scenario: The default applies when unset
- **WHEN** no recipient is configured
- **THEN** the notification is addressed to `me@jasonvertucio.com`

### Requirement: The site owner is not notified of their own comments
A notification SHALL NOT be dispatched when the comment's author is the site owner.

#### Scenario: The owner comments on their own post
- **WHEN** the site owner submits a comment
- **THEN** no notification job is dispatched

#### Scenario: Someone else comments
- **WHEN** a visitor or a non-owner user submits a comment
- **THEN** a notification job is dispatched

### Requirement: The notification renders without a post relation
The notification template SHALL render successfully when a comment's `post` relation is null, rather than raising on a null dereference.

#### Scenario: A comment with no post
- **WHEN** a notification is rendered for a comment whose `post_id` is null
- **THEN** the template renders with a fallback in place of the post title and does not raise

#### Scenario: The commenter's address is a usable link
- **WHEN** a notification renders an anonymous commenter's email address as a link
- **THEN** the link carries a `mailto:` scheme
