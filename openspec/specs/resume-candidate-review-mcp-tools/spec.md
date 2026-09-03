# resume-candidate-review-mcp-tools

## Purpose

Lets an authorized AI Persona review and publish pending resume edit candidates entirely from chat — listing every pending revision for the live resume version and approving one by revision number — gated identically to the persona's existing resume-edit tool and delegating to the same service logic the web-form review path uses.

## Requirements

### Requirement: MCP tools for reviewing pending resume candidates are gated identically to update-resume-section

The system SHALL expose MCP tools that let an authorized AI Persona list pending resume edit candidates and approve one, gated the same way as the existing `update-resume-section` tool: available only when both (a) the conversation's `AiSystem` includes the tool in its `allowed_tools`, and (b) the conversation's user holds the `edit-resume` Keystone permission. If either condition is not met, the tool SHALL be hidden entirely.

#### Scenario: Tools are invisible when the AiSystem does not allow them

- **WHEN** a chat conversation's `AiSystem` does not include the list or approve tool in its `allowed_tools`
- **THEN** that tool does not appear among the tools available to the persona for that conversation, regardless of the user's permissions

#### Scenario: Tools are invisible to an unauthorized or anonymous visitor

- **WHEN** a chat conversation's `AiSystem` allows the tool, but the conversation's user is unauthenticated or lacks `edit-resume`
- **THEN** the tool does not appear among the tools available to the persona for that conversation

#### Scenario: Tool call by an unauthorized caller is rejected

- **WHEN** either tool is somehow invoked for a conversation whose user lacks `edit-resume`
- **THEN** the tool returns an error response and makes no change to any resume data

### Requirement: A tool lists every pending candidate for the live resume version

The system SHALL expose an MCP tool that returns every `pending` resume edit candidate branched from the current live resume version, identified by revision number (not database ID). Each entry SHALL include the revision number, last-edited timestamp, and status. The response SHALL also include a suggested version for approval (the live version's patch segment incremented by one), computed the same way the web form's default is.

#### Scenario: Listing multiple pending candidates

- **WHEN** an authorized persona calls the list tool
- **AND** more than one `pending` candidate exists for the live resume version
- **THEN** the response includes one entry per pending candidate, each with its revision number, last-edited timestamp, and status
- **AND** the response includes a suggested version for approval

#### Scenario: Listing when nothing is pending

- **WHEN** an authorized persona calls the list tool
- **AND** no `pending` candidate exists for the live resume version
- **THEN** the response indicates an empty list without error

### Requirement: A tool approves a pending candidate by revision number with a caller-supplied version

The system SHALL expose an MCP tool that approves a `pending` resume edit candidate for the live resume version, identified by revision number, given a version to publish it as. The tool SHALL validate the revision number resolves to a `pending` candidate for the live version and SHALL delegate version validation and materialization to the same service logic the web-form approval path uses (format check, strictly-greater-than-base check, sibling rejection, DOCX/PDF regeneration). A successful approval SHALL be recorded as a tagged message on the conversation transcript, consistent with how a persona-initiated edit is recorded.

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
