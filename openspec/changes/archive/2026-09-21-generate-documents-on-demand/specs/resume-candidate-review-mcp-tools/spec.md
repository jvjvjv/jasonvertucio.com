## MODIFIED Requirements

### Requirement: A tool approves a pending candidate by revision number with a caller-supplied version

The system SHALL expose an MCP tool that approves a `pending` resume edit candidate for the live resume version, identified by revision number, given a version to publish it as. The tool SHALL validate the revision number resolves to a `pending` candidate for the live version and SHALL delegate version validation and materialization to the same service logic the web-form approval path uses (format check, strictly-greater-than-base check, sibling rejection, cached-document invalidation). A successful approval SHALL be recorded as a tagged message on the conversation transcript, consistent with how a persona-initiated edit is recorded.

#### Scenario: Successful approval via the tool

- **WHEN** an authorized persona calls the approve tool with a pending candidate's revision number and a valid version
- **THEN** the candidate is approved and materialized as the new live resume version, exactly as the web-form approval path behaves
- **AND** a tagged message recording the approval is appended to the conversation transcript

#### Scenario: Approve tool rejects an unknown or non-pending revision number

- **WHEN** an authorized persona calls the approve tool with a revision number that does not resolve to a `pending` candidate for the live resume version
- **THEN** the tool returns an error response
- **AND** no resume data changes

#### Scenario: Approve tool rejects an invalid version the same way the web form does

- **WHEN** an authorized persona calls the approve tool with a version that fails the format or strictly-greater-than-base validation
- **THEN** the tool returns an error response describing the requirement
- **AND** the candidate remains `pending`
