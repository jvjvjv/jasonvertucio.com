## Purpose

Bounds the unauthenticated `/posts` ingest endpoint so that it cannot be used to
fill the log volume or flood the application.

## Requirements

### Requirement: Ingest requests are rate limited per client

The ingest endpoint SHALL limit the number of requests one client address may
make per minute and SHALL answer 429 beyond it. The client address SHALL be the
visitor's address as reported through the site's proxy.

#### Scenario: Within the limit

- **WHEN** a client sends a request within the limit
- **THEN** the response is 204

#### Scenario: Beyond the limit

- **WHEN** a client exceeds the per-minute limit
- **THEN** further requests receive 429 and nothing is logged for them beyond the refusal

### Requirement: Ingest payloads are size-bounded

The ingest endpoint SHALL refuse a request whose body exceeds the configured
maximum with 413, and SHALL NOT write any part of a refused body to a log.

#### Scenario: An oversized body

- **WHEN** a request body is larger than the configured maximum
- **THEN** the response is 413 and the body is not logged

### Requirement: Logged ingest entries are bounded

A logged ingest entry SHALL be limited in total size, with a longer payload
truncated and marked as truncated, and the ingest log SHALL be rotated so that it
cannot grow without limit.

#### Scenario: A payload near the maximum

- **WHEN** an accepted request carries a payload longer than the per-entry log limit
- **THEN** the log entry holds the truncated payload and a marker that it was truncated
