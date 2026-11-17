## ADDED Requirements

### Requirement: Ghosted applications in metrics

The dashboard SHALL report an application's outcome as Ghosted when its latest status-history entry is `ghosted` and its history holds no `accepted`, `hired`, `rejected` or `withdrawn` entry. An application whose company replied after it was ghosted SHALL be reported by its later status, not as Ghosted. A `ghosted` entry SHALL NOT count as a response from the employer, SHALL NOT advance an application through the funnel, and SHALL be skipped when finding an application's first response. A ghosted application SHALL stay in Total Applied and in every rate's denominator. On the Timeline, a ghosted application's last segment SHALL be a ghosted segment that starts at its `ghosted` entry and runs to today.

#### Scenario: Ghosted outcome

- **WHEN** the selected period contains an application whose history is `applied` then `ghosted`
- **THEN** the Outcomes chart counts it under Ghosted and the ghosted rate includes it

#### Scenario: Ghosted after an interview

- **WHEN** an application's history is `applied`, `interviewing`, `interviewed`, then `ghosted`
- **THEN** it counts as responded and as interviewed, appears in the funnel up to Interviewed, and its outcome is Ghosted
- **AND** its days to first response are measured to the `interviewing` entry

#### Scenario: Ghosting is not a response

- **WHEN** an application's history is `applied` then `ghosted`
- **THEN** it does not raise the response rate
- **AND** it contributes nothing to the average days to first response

#### Scenario: Company replied after ghosting

- **WHEN** an application's history is `applied`, `ghosted`, then `rejected`
- **THEN** its outcome is Rejected, not Ghosted

#### Scenario: Interview after ghosting

- **WHEN** an application's history is `applied`, `ghosted`, then `interviewing`
- **THEN** its outcome is in progress, not Ghosted
- **AND** its days to first response are measured to the `interviewing` entry

#### Scenario: Silent but not yet marked

- **WHEN** an application's history is a single `applied` entry older than the ghosted threshold
- **THEN** its outcome is in progress until a `ghosted` entry is added

#### Scenario: Ghosted on the timeline

- **WHEN** the Timeline shows an application whose history is `applied` on day 1 then `ghosted` on day 31
- **THEN** it shows an applied segment from day 1 to day 31 and a ghosted segment from day 31 to today, and its outcome is labelled ghosted
