## ADDED Requirements

### Requirement: Withdrawn applications in metrics

An application the admin withdrew SHALL remain a counted applied application, and SHALL be reported as its own outcome rather than as rejected, ghosted or in progress. Withdrawing SHALL NOT count as a response from the employer. An application withdrawn after it reached a later stage SHALL still count toward that stage in the funnel and in the rates that stage drives.

#### Scenario: Withdrawn outcome

- **WHEN** the selected period contains an application whose history is `applied` then `withdrawn`
- **THEN** the Outcomes chart shows it under a "Withdrawn" bucket
- **AND** it is not counted as rejected, ghosted or in progress

#### Scenario: Withdrawn stays in the totals

- **WHEN** the selected period contains one withdrawn application and one other applied application
- **THEN** Total Applied is 2 and every rate is computed over both

#### Scenario: Withdrawing is not a response

- **WHEN** an application's history is `applied` then `withdrawn`
- **THEN** it does not raise the response rate
- **AND** it contributes nothing to the average days to first response

#### Scenario: Withdrawn after interviewing

- **WHEN** an application's history is `applied`, `interviewing`, then `withdrawn`
- **THEN** it counts as responded and as interviewed, appears in the funnel up to Interviewing, and its outcome is Withdrawn
- **AND** its days to first response are measured to the `interviewing` entry

#### Scenario: Withdrawn on the timeline

- **WHEN** the Timeline shows a withdrawn application
- **THEN** its outcome is labelled withdrawn and its last segment ends at the withdrawal date rather than running to today
