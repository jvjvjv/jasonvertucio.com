## ADDED Requirements

### Requirement: Duplicating an AiSystem marks it as pending its first edit
When an AiSystem is duplicated, the resulting clone SHALL be created with a `duplicated_at` timestamp set, distinguishing it from systems that have already been through a real configuration edit.

#### Scenario: Duplicating a system sets the pending marker
- **WHEN** an admin duplicates an existing AiSystem
- **THEN** the new AiSystem record is created with `duplicated_at` set to the time of duplication
- **AND** all other fields are copied from the original except `id` and `name` (which gets a "(copy)" suffix), matching current duplication behavior

### Requirement: A pending duplicate's first edit allows changing Provider, Model, and API Key
While an AiSystem's `duplicated_at` is set, its Edit page SHALL allow changing Provider, Model, and API Key, validated the same way the Create form validates them, instead of applying the normal locked-after-creation behavior.

#### Scenario: Editing a freshly duplicated system
- **WHEN** an admin opens the Edit page for an AiSystem whose `duplicated_at` is set
- **THEN** the Provider, Model, and API Key fields are enabled and editable
- **AND** submitting new values for those fields is accepted and validated using the same rules as creating a new AiSystem

#### Scenario: Editing an established system
- **WHEN** an admin opens the Edit page for an AiSystem whose `duplicated_at` is not set
- **THEN** the Provider, Model, and API Key fields remain disabled, matching current behavior

### Requirement: The pending marker clears after the first save
The first successful update to a pending duplicate SHALL clear its `duplicated_at` marker, after which the system is locked like any other established AiSystem, regardless of whether Provider, Model, or API Key were actually changed in that save.

#### Scenario: First save clears the pending state
- **WHEN** an admin submits the Edit form for an AiSystem whose `duplicated_at` is set, without changing Provider, Model, or API Key
- **THEN** the save succeeds
- **AND** the AiSystem's `duplicated_at` is cleared
- **AND** subsequent visits to its Edit page lock Provider, Model, and API Key

#### Scenario: A never-edited duplicate stays pending indefinitely
- **WHEN** a duplicated AiSystem has not yet been through any update
- **THEN** its `duplicated_at` marker remains set no matter how much time has passed
- **AND** its Edit page continues to allow changing Provider, Model, and API Key until the first save occurs

### Requirement: Duplication behavior is consistent between the app and the code-talker package
The package's `AiSystemManager::duplicate()` method SHALL set the same `duplicated_at` marker as the app's duplication controller action, so any consumer of the package observes the same first-edit behavior.

#### Scenario: Duplicating via the package service directly
- **WHEN** a host application calls `AiSystemManager::duplicate()` on an AiSystem
- **THEN** the resulting clone has `duplicated_at` set, identically to duplicating through the admin UI
