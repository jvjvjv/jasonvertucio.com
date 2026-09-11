## Purpose

Regenerates the resume's downloadable DOCX and PDF artifacts asynchronously whenever a new resume version becomes live, decoupling that slow, external-process-backed work from the request or tool call that published the version, while still recording and surfacing a generation failure.

## ADDED Requirements

### Requirement: Publishing a new live resume version triggers asynchronous document regeneration

Whenever a resume edit candidate is approved and materialized as the new live (`is_current`) resume version, the system SHALL trigger DOCX and PDF regeneration for that version without making the publishing request or tool call wait for generation to finish. The publishing action SHALL be considered complete, and SHALL report success to its caller, once the new version is live — independent of whether document generation has started, is in progress, or has finished.

#### Scenario: Approval response does not wait on document generation

- **WHEN** a pending resume edit candidate is approved with a valid version
- **THEN** the approval completes and reports success as soon as the new version is live
- **AND** the approving caller's response is not delayed by DOCX/PDF generation

#### Scenario: Document regeneration still happens after a successful approval

- **WHEN** a resume edit candidate is approved and materialized as the new live version
- **THEN** DOCX and PDF regeneration for that version is subsequently performed, whether or not the approving caller is still connected

### Requirement: Document generation outcome is recorded on the resume version

Each resume version SHALL carry a document generation status reflecting the outcome of its most recent generation attempt: pending (not yet attempted or in progress), succeeded, or failed. When generation fails, the system SHALL also record a human-readable error describing the failure. This record SHALL be the durable source of truth for whether a version's downloadable documents are known-good, since the action that triggered generation no longer waits for the result.

#### Scenario: New version starts with a pending generation status

- **WHEN** a resume edit candidate is approved and a new resume version becomes live
- **THEN** the new version's document generation status is recorded as pending until generation runs

#### Scenario: Successful generation is recorded

- **WHEN** DOCX and PDF regeneration for a resume version both complete successfully
- **THEN** that version's document generation status is recorded as succeeded
- **AND** any previously recorded generation error for that version is cleared

#### Scenario: Failed generation is recorded with an error

- **WHEN** DOCX or PDF regeneration for a resume version fails
- **THEN** that version's document generation status is recorded as failed
- **AND** a description of the failure is recorded alongside it

### Requirement: A document generation failure triggers an owner notification

When asynchronous document generation for a newly-published resume version fails, the system SHALL notify the configured notification recipient by email, since no synchronous caller is waiting to observe the failure directly.

#### Scenario: Failure email is sent

- **WHEN** DOCX or PDF regeneration for a newly-published resume version fails
- **THEN** an email describing the failure and identifying the affected version is sent to the configured notification recipient

#### Scenario: No failure email on success

- **WHEN** DOCX and PDF regeneration for a newly-published resume version both succeed
- **THEN** no failure notification email is sent
