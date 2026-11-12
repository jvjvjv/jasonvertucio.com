## MODIFIED Requirements

### Requirement: Application statuses

An Application's status SHALL be one of `draft`, `passed`, `applied`, `interviewing`, `interviewed`, `offered`, `accepted`, `hired`, `rejected`, `withdrawn`. `accepted`, `hired`, `rejected` and `withdrawn` are terminal. An application in `applied` status whose latest status-history entry is older than the configured ghosted threshold SHALL be displayed as "ghosted" without changing its stored status. Having a targeted resume SHALL NOT be a status.

#### Scenario: Passing on a job

- **WHEN** the admin marks a `draft` application as passed
- **THEN** its status becomes `passed`

#### Scenario: Applying after passing

- **WHEN** the admin marks a `passed` application as applied
- **THEN** its status becomes `applied` with an `applied` status-history entry

#### Scenario: Ghosted display

- **WHEN** an `applied` application's latest status-history entry is older than the ghosted threshold
- **THEN** lists and the Discussion page display it as "ghosted"
- **AND** its stored status is still `applied`

#### Scenario: Filtering by withdrawn

- **WHEN** the admin opens the Applications list filters
- **THEN** `withdrawn` is one of the statuses offered by the status filter

## ADDED Requirements

### Requirement: Withdrawing an application

The admin SHALL be able to record that they withdrew an application from consideration by adding a `withdrawn` status-history entry, with an optional date and notes, as for any other pipeline status. `withdrawn` SHALL be offered as a next status for an application in `applied`, `interviewing`, `interviewed` or `offered`, and SHALL NOT be offered for an application in `draft` or `passed` (not having applied is recorded by passing). Once withdrawn, the application SHALL accept no further status-history entries. A withdrawn application SHALL never be displayed as "ghosted". The application's AI session SHALL be able to record a withdrawal through its status-update tool when the admin reports one in the chat.

#### Scenario: Withdrawing after applying

- **WHEN** the admin adds a `withdrawn` entry with notes to an `applied` application
- **THEN** the entry is stored with its notes and date and the application's status becomes `withdrawn`

#### Scenario: Withdrawing after an interview

- **WHEN** the admin opens the Discussion page of an `interviewed` application
- **THEN** the status-history form offers `withdrawn` among the next statuses

#### Scenario: Not offered before applying

- **WHEN** the admin opens the Discussion page of a `draft` or `passed` application
- **THEN** the status-history form does not offer `withdrawn`

#### Scenario: Withdrawn is terminal

- **WHEN** a request adds any status-history entry to a `withdrawn` application
- **THEN** the request is refused and nothing is stored
- **AND** the status-history form offers no next statuses

#### Scenario: Undoing a withdrawal

- **WHEN** the admin deletes the `withdrawn` entry of an application whose previous entry was `interviewing`
- **THEN** the application's status becomes `interviewing` again

#### Scenario: Withdrawn is never ghosted

- **WHEN** a `withdrawn` application's latest status-history entry is older than the ghosted threshold
- **THEN** lists and the Discussion page display it as `withdrawn`, not "ghosted"

#### Scenario: Agent records a withdrawal

- **WHEN** the admin tells the application's AI session that they withdrew their application
- **THEN** the session's status-update tool accepts `withdrawn` and the application's status becomes `withdrawn` with a matching history entry
