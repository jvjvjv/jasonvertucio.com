## Purpose

Automatically bans an IP address that has repeatedly produced comments determined to be spam, so a moderator does not have to notice and act on the pattern by hand every time, reacting to a `CommentMarkedAsSpam` event regardless of what produced it.

## ADDED Requirements

### Requirement: Repeat spam from one IP triggers an IP ban
When a `CommentMarkedAsSpam` event is handled, the system SHALL count comments with `is_spam = true` and a matching `ip_address` created within a configurable rolling time window. When that count meets or exceeds a configurable threshold, the system SHALL create an `IpBan` record for that `ip_address`.

#### Scenario: Threshold is reached
- **WHEN** the count of spam comments from an IP within the configured window reaches the configured threshold
- **THEN** an `IpBan` row is created for that IP address

#### Scenario: Threshold is not yet reached
- **WHEN** the count of spam comments from an IP within the configured window is below the configured threshold
- **THEN** no `IpBan` row is created

#### Scenario: Spam comments outside the window do not count
- **WHEN** an IP has enough spam comments to meet the threshold only by including comments older than the configured window
- **THEN** no `IpBan` row is created

### Requirement: Escalation does not double-ban an already-banned IP
The system SHALL NOT create a second `IpBan` row for an `ip_address` that already has one.

#### Scenario: The IP is already banned
- **WHEN** a `CommentMarkedAsSpam` event is handled for a comment whose `ip_address` already has an `IpBan` row
- **THEN** no additional `IpBan` row is created for that address

### Requirement: Threshold and window are configurable
The spam count threshold and the rolling time window used to evaluate it SHALL be read from application configuration rather than hardcoded, and SHALL each have a default value applied when unset.

#### Scenario: Configured values are honored
- **WHEN** a threshold and window are set in configuration
- **THEN** escalation evaluates against those values

#### Scenario: Defaults apply when unset
- **WHEN** no threshold or window is configured
- **THEN** escalation evaluates against documented default values

### Requirement: Escalation reacts to the event, not to who or what raised it
The escalation behavior SHALL be triggered solely by the `CommentMarkedAsSpam` event and SHALL NOT depend on which part of the system dispatched it.

#### Scenario: A manually-moderated spam comment triggers evaluation
- **WHEN** a moderator's action results in a `CommentMarkedAsSpam` event being dispatched
- **THEN** escalation evaluates that comment's `ip_address` for a ban exactly as it would for any other dispatch of the same event

### Requirement: Escalation failure does not affect the moderation action
A failure while evaluating or creating an `IpBan` SHALL NOT prevent or roll back the comment's spam-marking, and SHALL NOT be surfaced to the moderator as a failure of the moderation action.

#### Scenario: Escalation processing fails
- **WHEN** an error occurs while counting spam comments or creating an `IpBan` row
- **THEN** the comment remains marked as spam and the moderator's request completes successfully
