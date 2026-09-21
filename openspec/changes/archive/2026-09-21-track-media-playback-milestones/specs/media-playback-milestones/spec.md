## Purpose

Defines when a Jellyfin playback webhook counts as "finished watching" and the independent, unrelated consumers that react to that milestone without knowing about each other.

## ADDED Requirements

### Requirement: A playback milestone is reached at 90% of runtime
The system SHALL treat a playback progress webhook as a completion milestone for a given media item and playback session when the reported playback position is at least 90% of the reported runtime, and runtime is known and greater than zero. The system SHALL raise the milestone at most once per playback session, even if further progress webhooks continue to report positions past the threshold.

#### Scenario: Progress crosses the threshold
- **WHEN** a `PlaybackProgress` webhook reports a playback position at or above 90% of the item's runtime for a session that has not yet crossed the threshold
- **THEN** the milestone is raised for that item and session

#### Scenario: Progress is reported again after the threshold
- **WHEN** a subsequent `PlaybackProgress` webhook for the same session again reports a position at or above 90% of runtime
- **THEN** the milestone is not raised a second time for that session

#### Scenario: Runtime is unknown
- **WHEN** a playback webhook does not report a runtime, or reports a runtime of zero
- **THEN** no milestone is raised, regardless of the reported position

#### Scenario: Progress stays below the threshold
- **WHEN** a `PlaybackProgress` webhook reports a playback position below 90% of the item's runtime
- **THEN** no milestone is raised

### Requirement: A milestone independently updates a recently-finished history
Reaching a milestone SHALL cause the finished item to be recorded in a history of recently finished media, independent of whether any other consumer of the milestone succeeds or fails.

#### Scenario: A finished item is recorded
- **WHEN** a milestone is raised for a media item
- **THEN** that item, its media type, and the time it finished are retrievable from the recently-finished history afterward

#### Scenario: Recording history does not depend on other consumers
- **WHEN** a milestone is raised and a separate consumer of the same milestone fails
- **THEN** the recently-finished history is still updated

### Requirement: A milestone independently invalidates currently-watching state
Reaching a milestone SHALL cause any cached "currently watching" state for that item to be invalidated, so a finished item does not continue to display as currently playing for the remainder of a cache lifetime.

#### Scenario: Cached currently-watching state is cleared
- **WHEN** a milestone is raised for the item currently represented in the "currently watching" cache
- **THEN** the next read of "currently watching" state reflects the finished item's completed status rather than serving a stale cached entry

#### Scenario: Cache invalidation does not depend on other consumers
- **WHEN** a milestone is raised and the recently-finished history fails to update
- **THEN** the currently-watching cache is still invalidated

### Requirement: A milestone independently reaches a stubbed social-post consumer
Reaching a milestone SHALL notify a placeholder consumer representing a future "post to social" integration. This consumer SHALL NOT perform any outbound network call, and SHALL NOT publish anything to any external service.

#### Scenario: The stub observes the milestone without side effects
- **WHEN** a milestone is raised
- **THEN** the stubbed social-post consumer is invoked and no outbound request to any social platform occurs

### Requirement: Milestone consumers are independent and unaware of one another
Consumers of the milestone SHALL be able to be added, removed, or fail individually without requiring changes to the webhook handling path, to the `LocalMedia` model, or to any other consumer.

#### Scenario: One consumer fails without blocking the others
- **WHEN** a milestone is raised and one consumer throws an error
- **THEN** the webhook request still completes successfully and the remaining consumers still run
