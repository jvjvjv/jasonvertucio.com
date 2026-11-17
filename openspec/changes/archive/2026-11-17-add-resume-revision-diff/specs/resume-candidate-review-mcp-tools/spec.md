## MODIFIED Requirements

### Requirement: A tool lists every pending candidate for the live resume version

The system SHALL expose an MCP tool that returns every `pending` resume edit candidate branched from the current live resume version, identified by revision number (not database ID). Each entry SHALL include the revision number, last-edited timestamp, and status. Each entry SHALL also include its changes compared with the live resume version, determined the same way the review page determines them against the base version.
- Each entry SHALL include the number of changes.
- Each change SHALL give its section, the entry it concerns, the field, its kind (added, removed, modified, moved or order changed), and the value before and after.
- A change to a field the resume does not display SHALL be flagged as not shown.

The response SHALL also include a suggested version for approval (the live version's patch segment incremented by one), computed the same way the web form's default is.

#### Scenario: Listing multiple pending candidates

- **WHEN** an authorized persona calls the list tool
- **AND** more than one `pending` candidate exists for the live resume version
- **THEN** the response includes one entry per pending candidate, each with its revision number, last-edited timestamp, status, change count and changes
- **AND** the response includes a suggested version for approval

#### Scenario: Listing when nothing is pending

- **WHEN** an authorized persona calls the list tool
- **AND** no `pending` candidate exists for the live resume version
- **THEN** the response indicates an empty list without error

#### Scenario: Candidate changes are described

- **WHEN** an authorized persona calls the list tool
- **AND** a pending candidate rewords one experience bullet and adds one skill compared with the live version
- **THEN** that candidate's entry reports a change count of 2
- **AND** its changes include a modified experience bullet with its before and after text, and an added skill naming its category

#### Scenario: Candidate identical to the live version

- **WHEN** a pending candidate's data equals the live version's
- **THEN** its entry reports a change count of 0 and an empty change list

#### Scenario: Hidden field change is flagged

- **WHEN** a pending candidate changes a job's salary
- **THEN** that change is included and flagged as not shown on the resume
