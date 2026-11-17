## Purpose

Records when a company goes silent on an application by marking it ghosted, and remembers which companies are currently ghosting the site owner so their other postings rank lower.

## ADDED Requirements

### Requirement: Silent applications are marked ghosted automatically

The system SHALL, once a day, mark as ghosted every non-deleted Application whose status is `applied` or `interviewed` and whose latest status-history entry is dated more than the configured ghosted threshold before now. The threshold SHALL be configurable and SHALL default to 30 days. Marking SHALL add a `ghosted` status-history entry and set the application's status to `ghosted`. The entry SHALL be dated the day the threshold was crossed (the latest entry's date plus the threshold), not the day the run happened, and its notes SHALL state that it was recorded automatically after that many days without an update. An application whose status or latest entry changed before it was marked SHALL be judged on its new state. The same run SHALL be startable by hand, with a preview mode that lists the applications it would mark and writes nothing.

#### Scenario: Silent after applying

- **WHEN** an `applied` application's latest entry is dated 31 days ago and the threshold is 30 days
- **THEN** the run adds a `ghosted` entry dated 1 day ago and the application's status becomes `ghosted`

#### Scenario: Silent after an interview

- **WHEN** an `interviewed` application's latest entry is dated 45 days ago and the threshold is 30 days
- **THEN** the run adds a `ghosted` entry dated 15 days ago and the application's status becomes `ghosted`

#### Scenario: Not silent long enough

- **WHEN** an `applied` application's latest entry is dated 29 days ago and the threshold is 30 days
- **THEN** the run leaves it `applied` and adds no entry

#### Scenario: Configured threshold

- **WHEN** the threshold is configured as 14 days and an `applied` application's latest entry is dated 15 days ago
- **THEN** the run marks it ghosted

#### Scenario: Statuses that are never marked

- **WHEN** an application is `draft`, `passed`, `interviewing`, `offered`, `accepted`, `hired`, `rejected`, `withdrawn` or already `ghosted`, however old its latest entry
- **THEN** the run adds no entry and leaves its status unchanged

#### Scenario: Deleted application

- **WHEN** a soft-deleted application is `applied` with a latest entry older than the threshold
- **THEN** the run does not mark it

#### Scenario: Running twice

- **WHEN** the run executes twice on the same day
- **THEN** each silent application has exactly one `ghosted` entry

#### Scenario: Updated before being marked

- **WHEN** the admin adds an `interviewing` entry to a silent `applied` application after the run selected it but before it was marked
- **THEN** the run does not mark it and its status stays `interviewing`

#### Scenario: Preview

- **WHEN** the run is started by hand in preview mode
- **THEN** it lists each application it would mark with the date it would use
- **AND** no entry is added and no status changes

### Requirement: Ghosted can be recorded by hand

The admin SHALL be able to record that an application was ghosted before the threshold passes, by adding a `ghosted` status-history entry with an optional date and notes. `ghosted` SHALL be offered as a next status for an application in `applied` or `interviewed`, and SHALL NOT be offered for any other status. The application's AI session SHALL be able to record it through its status-update tool when the admin reports being ghosted in the chat.

#### Scenario: Marking ghosted early

- **WHEN** the admin adds a `ghosted` entry to an `applied` application whose latest entry is 10 days old
- **THEN** the entry is stored with its date and notes and the application's status becomes `ghosted`

#### Scenario: Offered after an interview

- **WHEN** the admin opens the Discussion page of an `interviewed` application
- **THEN** the status-history form offers `ghosted` among the next statuses

#### Scenario: Not offered while interviewing

- **WHEN** the admin opens the Discussion page of an `interviewing` or `offered` application
- **THEN** the status-history form does not offer `ghosted`

#### Scenario: Agent records being ghosted

- **WHEN** the admin tells the application's AI session that the company ghosted them
- **THEN** the session's status-update tool accepts `ghosted` and the application's status becomes `ghosted` with a matching history entry

### Requirement: A ghosted application can move on

`ghosted` SHALL NOT be terminal. A ghosted application SHALL accept further status-history entries, and the status-history form SHALL offer `interviewing`, `offered`, `rejected` and `withdrawn` as its next statuses. Adding one SHALL set the application's status to it. Deleting the `ghosted` entry SHALL return the application to its previous entry's status, and the daily run SHALL mark it again if it is still silent past the threshold.

#### Scenario: Company replies with an interview

- **WHEN** the admin adds an `interviewing` entry to a `ghosted` application
- **THEN** the application's status becomes `interviewing`

#### Scenario: Late rejection

- **WHEN** the admin adds a `rejected` entry to a `ghosted` application
- **THEN** the application's status becomes `rejected` and it accepts no further entries

#### Scenario: Removing an automatic mark

- **WHEN** the admin deletes the `ghosted` entry of an application whose previous entry is an `applied` entry from 40 days ago, with a 30-day threshold
- **THEN** the application's status becomes `applied`
- **AND** the next daily run marks it ghosted again

### Requirement: Company ghosting tally

The system SHALL count, for each company, how many non-deleted Applications at that company currently have the status `ghosted`. Two Applications SHALL be at the same company when their Role intel refers to the same researched Company, or when their company names are equal ignoring letter case and surrounding whitespace, or when one's company name equals the other's researched Company name on the same terms. An Application SHALL leave the tally as soon as its status is no longer `ghosted`, and SHALL NOT count when it is deleted. An Application's own ghosting SHALL NOT count toward its own tally. An Application whose company is still the unknown-company placeholder SHALL NOT be matched to any other by name.

#### Scenario: Unknown company

- **WHEN** a `ghosted` application and a `draft` application both still carry the unknown-company placeholder as their company name
- **THEN** the `draft` application's tally is 0

#### Scenario: One ghosting at a company

- **WHEN** one application at "Acme" is `ghosted` and another application at "acme " is `draft`
- **THEN** the `draft` application's company tally is 1

#### Scenario: Matched through researched company

- **WHEN** a `ghosted` application's company name is "Acme Corp", its Role intel refers to the Company "Acme", and another application's company name is "Acme"
- **THEN** the other application's company tally is 1

#### Scenario: Company replied later

- **WHEN** a `ghosted` application at "Acme" gets a `rejected` entry
- **THEN** it no longer counts toward the tally of other "Acme" applications

#### Scenario: Deleted ghosted application

- **WHEN** the only `ghosted` application at "Acme" is deleted
- **THEN** other "Acme" applications have a tally of 0

#### Scenario: Own ghosting not counted

- **WHEN** an application is the only `ghosted` application at its company
- **THEN** its own tally is 0

#### Scenario: Different companies

- **WHEN** one application at "Acme" is `ghosted`
- **THEN** an application at "Globex" has a tally of 0

### Requirement: Companies that ghost rank lower

For an Application whose company tally is above zero, the system SHALL show a "Ghosted you N×" marker, where N is the tally. When the Application has a fit score, the system SHALL also show an adjusted fit score: the stored fit score minus the configured penalty for each counted ghosting, never below 1. The penalty SHALL be configurable and SHALL default to 10 points. The stored fit score SHALL NOT change, and the stored score SHALL remain visible beside the adjusted one. The Applications list and the Discussion page SHALL both show the marker and the adjusted score. An Application with a tally of zero SHALL show its fit score as before, with no marker.

#### Scenario: Penalised fit score

- **WHEN** an application with a fit score of 78 is at a company with a tally of 2 and the penalty is 10
- **THEN** the list and the Discussion page show a fit score of 58, the stored 78, and "Ghosted you 2×"
- **AND** the stored fit score is still 78

#### Scenario: Floor of 1

- **WHEN** an application with a fit score of 15 is at a company with a tally of 3 and the penalty is 10
- **THEN** the adjusted fit score shown is 1

#### Scenario: No fit score yet

- **WHEN** a `draft` application with no fit score is at a company with a tally of 1
- **THEN** it shows "Ghosted you 1×" and no fit score

#### Scenario: Configured penalty

- **WHEN** the penalty is configured as 25 and an application with a fit score of 80 is at a company with a tally of 1
- **THEN** the adjusted fit score shown is 55

#### Scenario: Penalty lifts when the company replies

- **WHEN** the only `ghosted` application at a company moves to `interviewing`
- **THEN** the company's other applications show their stored fit score and no marker

### Requirement: The AI session knows the company's ghosting history

The context the application's AI session reads about its application SHALL include the company's ghosting tally and the penalty the system applies for it. It SHALL also say that the penalty is applied separately, so the fit score the session records should reflect the fit of the role alone.

#### Scenario: Session context at a company that ghosted

- **WHEN** the session reads its application context and the company's tally is 1
- **THEN** the context reports a tally of 1, the penalty per ghosting, and that the penalty is applied outside the session's score

#### Scenario: Session context at a company with no ghosting

- **WHEN** the session reads its application context and the company's tally is 0
- **THEN** the context reports a tally of 0
