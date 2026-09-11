## Purpose

Defines the fan-in event that fires whenever an IP is banned, regardless of which producer created the ban, and the uniform alerting reaction and manual admin ban action built on top of it.

## ADDED Requirements

### Requirement: An IpBanned event fires whenever an IP ban is created
The system SHALL dispatch a domain event whenever a new IP ban record is created, independent of which code path created it. This SHALL hold for the WordPress honeypot ban path, the manual admin ban action described below, and any future producer (e.g. a repeated-spam escalation path) that creates an IP ban record through the same model.

#### Scenario: A honeypot request triggers the event
- **WHEN** a request to `/wp-login.php` causes a new IP ban record to be created
- **THEN** the domain event fires with that IP and an indication of the honeypot as the source

#### Scenario: A manual admin ban triggers the event
- **WHEN** a moderator uses the manual "ban this IP" action and a new IP ban record is created
- **THEN** the domain event fires with that IP and an indication of the manual admin action as the source

#### Scenario: A duplicate ban attempt does not re-fire the event
- **WHEN** an IP that is already banned is submitted again to a producer that uses an existing-or-create operation
- **THEN** no new IP ban record is created and the domain event does not fire again

### Requirement: A moderator can manually ban the IP behind a comment
The comment-moderation admin UI SHALL offer an action that creates an IP ban record for the IP address recorded against a given comment. This action SHALL be gated on the same `manage-blog` permission that gates the rest of comment moderation.

#### Scenario: A permitted moderator bans an IP from a comment
- **WHEN** a user holding `manage-blog` triggers "ban this IP" on a comment with a recorded IP address
- **THEN** an IP ban record is created for that IP address and the domain event fires

#### Scenario: An unpermitted user cannot trigger the action
- **WHEN** a user without `manage-blog` attempts to trigger the ban-IP action
- **THEN** the request is refused and no IP ban record is created

#### Scenario: A comment with no recorded IP address cannot be used to ban
- **WHEN** a moderator triggers "ban this IP" on a comment that has no recorded `ip_address`
- **THEN** no IP ban record is created and the moderator is shown an error

### Requirement: The ban alert reaction is uniform across all producers
A listener SHALL react to the IP-ban domain event by recording a structured security-audit log entry containing at minimum the banned IP and the source that triggered the ban. The same reaction SHALL run regardless of which producer dispatched the event, so no producer needs its own alerting logic.

#### Scenario: Honeypot-triggered and manually-triggered bans produce equivalent audit entries
- **WHEN** one IP ban record originates from the honeypot path and another originates from the manual admin action
- **THEN** both result in a structured security-audit log entry through the same listener, differing only in the recorded source

#### Scenario: The alert reaction does not block the request that created the ban
- **WHEN** the domain event's listener runs
- **THEN** the HTTP response for the request that triggered the ban is not delayed on the outcome of any external notification hook
