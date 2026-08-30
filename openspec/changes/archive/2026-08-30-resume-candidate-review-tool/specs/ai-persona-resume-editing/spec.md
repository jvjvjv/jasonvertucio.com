## MODIFIED Requirements

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
