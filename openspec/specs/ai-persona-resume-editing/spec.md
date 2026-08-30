# ai-persona-resume-editing

## Purpose

Lets an authorized AI Persona propose edits to the main resume through a mutating tool, batches those edits into time-windowed draft revisions instead of writing live, blocks manual editing of the live resume while a revision is pending, and lets a permitted human review a specific revision on the preview page and approve or permanently reject it.

## Requirements

### Requirement: Persona tool for editing the main resume is gated on both the AI system's allow-list and the user's permission

The system SHALL expose an MCP tool (or tool family) that lets an AI Persona edit the main resume's personal information, summary, skills, experience, education, projects, and technical profile. The tool SHALL only be available for a conversation when **both**: (a) the conversation's `AiSystem` includes the tool in its `allowed_tools`, and (b) the conversation's user holds the `edit-resume` Keystone permission. If either condition is not met, the tool SHALL be hidden entirely.

#### Scenario: Tool is invisible when the AiSystem does not allow it

- **WHEN** a chat conversation's `AiSystem` does not include the resume-edit tool in its `allowed_tools`
- **THEN** the resume-edit tool does not appear among the tools available to the persona for that conversation, regardless of the user's permissions

#### Scenario: Tool is invisible to an unauthorized or anonymous visitor

- **WHEN** a chat conversation's `AiSystem` allows the tool, but the conversation's user is unauthenticated or lacks `edit-resume`
- **THEN** the resume-edit tool does not appear among the tools available to the persona for that conversation

#### Scenario: Tool call by an unauthorized caller is rejected

- **WHEN** the resume-edit tool is somehow invoked for a conversation whose user lacks `edit-resume`
- **THEN** the tool returns an error response and makes no change to any resume data

#### Scenario: Authorized user with an allowing AiSystem edits the resume

- **WHEN** a conversation's `AiSystem` allows the tool and its user holds `edit-resume`, and the persona calls the resume-edit tool with a valid edit payload
- **THEN** the edit is applied to a draft candidate (per the batching requirement below), not to the live resume

### Requirement: Persona edits never mutate the live resume directly

The system SHALL NOT modify the currently active (`is_current`) resume version as a direct effect of a persona tool call. All persona edits SHALL be written to a draft "resume edit candidate" record.

#### Scenario: Live resume is unchanged immediately after a persona edit

- **WHEN** the persona successfully calls the resume-edit tool
- **THEN** the resume version marked `is_current` still reflects its pre-edit data
- **AND** the edit is reflected only in a resume edit candidate record

### Requirement: Consecutive persona edits within the batch window share one candidate

A persona edit SHALL be applied to the highest-revision `pending` candidate for that base version if that candidate's last edit occurred within a configurable rolling window (default 12 hours) of the new edit; otherwise a new candidate (next sequential revision) SHALL be created. An older candidate that is superseded this way is NOT automatically resolved — it remains `pending` (see the rejection requirement below), so more than one `pending` candidate MAY exist for the same base version at once.

#### Scenario: Second edit within the window updates the same candidate

- **WHEN** a pending candidate exists for the current base version with its last edit timestamped less than the configured window ago
- **AND** the persona calls the resume-edit tool again
- **THEN** the existing candidate's data is updated in place
- **AND** the candidate's revision number is unchanged
- **AND** the candidate's last-edited timestamp advances to now

#### Scenario: Edit after the window elapses starts a new candidate revision

- **WHEN** the pending candidate for the current base version's last edit occurred more than the configured window ago
- **AND** the persona calls the resume-edit tool
- **THEN** a new resume edit candidate is created for the same base version with the next sequential revision number
- **AND** the new candidate's starting data is the prior candidate's data (not the original base version's data), so no prior edits are lost
- **AND** the prior candidate's status is left as `pending` (not automatically resolved) — it now requires an explicit human rejection, per the rejection requirement below

#### Scenario: First-ever edit for a base version creates its first candidate

- **WHEN** no resume edit candidate exists yet for the current base version
- **AND** the persona calls the resume-edit tool
- **THEN** a new candidate is created seeded with the base version's current data, at revision number 1

#### Scenario: Editing after a candidate was approved starts a fresh candidate from the new live version

- **WHEN** the most recent candidate for a base version has status `approved`
- **AND** the persona calls the resume-edit tool again
- **THEN** a new candidate is created against the now-current live resume version (the one materialized from the approved candidate), not against the old base version

### Requirement: Manual resume edits are blocked while a candidate is pending

The system SHALL prevent the admin resume editor from saving changes to a resume version that has a `pending` resume edit candidate. The editor SHALL indicate that an AI-drafted revision is awaiting review instead of allowing the save.

#### Scenario: Manual save is rejected while a candidate is pending

- **WHEN** a `pending` resume edit candidate exists for the live resume version
- **AND** a human attempts to save an edit via the admin resume editor
- **THEN** the save is rejected
- **AND** the user is shown that a pending AI-drafted revision must be approved or rejected first

#### Scenario: Manual edits are allowed again once the candidate is resolved

- **WHEN** the previously pending candidate for the live resume version has since been approved or rejected
- **THEN** a human can save changes via the admin resume editor normally

### Requirement: Admin preview page can render a specific candidate revision

The admin resume preview page SHALL accept an optional revision identifier. When provided, and the requesting user holds `edit-resume`, the page SHALL render that resume edit candidate's full data (personal info, skills, experience, education, projects, technical profile) instead of the live resume. When omitted, the page SHALL render the live (`is_current`) resume as it does today.

#### Scenario: Authorized user views a pending candidate

- **WHEN** a user with `edit-resume` requests the preview page with a valid candidate identifier
- **THEN** the page renders that candidate's full resume data
- **AND** the page shows Approve and Reject actions if the candidate's status is `pending`

#### Scenario: Unauthorized user cannot view a candidate

- **WHEN** a user without `edit-resume` requests the preview page with a candidate identifier
- **THEN** the request is denied (not rendered) the same way the existing preview page denies unauthorized access today

#### Scenario: Preview page flags a stale candidate

- **WHEN** the requested candidate's base resume version is no longer the live (`is_current`) version
- **THEN** the preview page displays a warning that the live resume has changed since this candidate was branched, before allowing Approve

### Requirement: Approving a candidate materializes it as the new live resume version

The system SHALL let a user with `edit-resume` approve a `pending` resume edit candidate by supplying the version number the approved resume should be published as. The supplied version SHALL be validated against the `YYYY.MAJOR.MINOR` format and SHALL be rejected if it is not strictly greater than the candidate's base resume version (compared component-wise: year, then major, then minor). Approval SHALL create a new resume version using the supplied version number, mark it as the current (`is_current`) version, write the candidate's data into that version's related records, regenerate the resume's DOCX and PDF artifacts, and mark the candidate `approved`. Approval SHALL also permanently reject (delete) every other `pending` candidate branched from the same base resume version, since their snapshots were seeded from data this approval has now superseded and materializing one of them later would silently overwrite the approved version's data.

#### Scenario: Approving one candidate rejects its pending siblings

- **WHEN** more than one `pending` candidate exists for the same base resume version
- **AND** a user with `edit-resume` approves one of them with a valid version
- **THEN** that candidate is approved and materialized as described above
- **AND** every other `pending` candidate sharing the same base resume version is permanently deleted
- **AND** pending candidates for a *different* base resume version are left untouched

#### Scenario: Successful approval with the suggested default version

- **WHEN** a user with `edit-resume` approves a `pending` candidate, submitting the pre-filled suggested version (the base version's patch segment incremented by one)
- **THEN** a new resume version is created at that version number and becomes the current version
- **AND** the previously current version is no longer current
- **AND** the new version's data matches the approved candidate's snapshot
- **AND** DOCX and PDF files are regenerated for the new version
- **AND** the candidate's status becomes `approved`, with an approval timestamp and the approving user recorded

#### Scenario: Successful approval with a reviewer-chosen major or minor version

- **WHEN** a user with `edit-resume` approves a `pending` candidate, submitting a version whose major or minor segment exceeds the base version's (e.g. base `2026.1.4`, submitted `2026.2.0`)
- **THEN** the new resume version is created at the submitted version number
- **AND** the approval otherwise proceeds exactly as the default-version case

#### Scenario: Approval is rejected when the submitted version is not greater than the base version

- **WHEN** a user with `edit-resume` attempts to approve a `pending` candidate with a version that is equal to or less than the candidate's base resume version (by component-wise year/major/minor comparison, not string comparison)
- **THEN** the approval is rejected
- **AND** no new resume version is created
- **AND** the candidate remains `pending`

#### Scenario: Approval is rejected when the submitted version does not match the required format

- **WHEN** a user with `edit-resume` attempts to approve a `pending` candidate with a version string that does not match `YYYY.MAJOR.MINOR`
- **THEN** the approval is rejected
- **AND** no new resume version is created
- **AND** the candidate remains `pending`

#### Scenario: Approval succeeds even if document regeneration fails

- **WHEN** a candidate's data is successfully written to a new live resume version at the submitted version
- **AND** subsequent DOCX/PDF regeneration fails
- **THEN** the new version still becomes current with the approved data
- **AND** the user is shown the generation error instead of a silent failure

#### Scenario: Only a pending candidate can be approved

- **WHEN** a user attempts to approve a candidate whose status is not `pending` (already `approved`; a rejected candidate no longer exists to attempt this on)
- **THEN** the system rejects the approval action without changing the live resume

### Requirement: Rejecting a candidate permanently deletes it and leaves the live resume untouched

The system SHALL let a user with `edit-resume` reject a `pending` resume edit candidate. Rejection SHALL permanently delete the candidate's record (there is no `discarded`/soft-deleted state and no way to undo a rejection) without altering the live resume version. A human-initiated rejection SHALL always be an explicit, individual action on one candidate — the system SHALL NOT automatically reject a candidate on the basis of staleness or elapsed time. The sole exception is the side effect of approving a sibling candidate (same base resume version), per the approval requirement above.

#### Scenario: Successful rejection

- **WHEN** a user with `edit-resume` rejects a `pending` candidate
- **THEN** the candidate's record is permanently deleted
- **AND** the live resume version is unchanged

#### Scenario: Rejected candidate does not block future persona edits

- **WHEN** a candidate for a base version has been rejected (deleted)
- **AND** the persona calls the resume-edit tool again
- **THEN** a new candidate is created seeded from the current live resume version, since no pending candidate exists

#### Scenario: A stale pending candidate is never rejected automatically merely for being stale

- **WHEN** a `pending` candidate's last edit occurred long ago (including beyond the batching window)
- **AND** no sibling candidate for the same base version is approved
- **THEN** the system does not delete or otherwise resolve that candidate on its own
- **AND** the candidate remains `pending`, blocking manual edits to that base version, until a human explicitly approves or rejects it (or a sibling is approved, per the approval requirement above)

### Requirement: Persona resume edits are recorded in the conversation transcript

Every successful persona-initiated resume edit SHALL be recorded as a message on the conversation, tagged so it is distinguishable from ordinary chat turns, without triggering an additional synchronous agent turn.

#### Scenario: Edit appends a tagged conversation message

- **WHEN** the persona successfully calls the resume-edit tool
- **THEN** a new conversation message is created summarizing the edit
- **AND** the message is tagged identifying it as an AI resume edit
- **AND** no additional agent turn is triggered synchronously by recording this message
