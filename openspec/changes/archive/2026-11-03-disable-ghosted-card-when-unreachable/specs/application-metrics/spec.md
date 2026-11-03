## ADDED Requirements

### Requirement: Ghosted rate availability

The dashboard SHALL treat the ghosted rate as not applicable when the selected period's start date is on or after the date that is the configured ghosted threshold number of days before today, because applications in such a period have not had time to go ghosted. When it is not applicable, the Ghosted card SHALL remain in the KPI row, appear greyed out, show "—" instead of a percentage, and state why. A period with no start date SHALL always have an applicable ghosted rate. The other KPI cards, the Outcomes chart and the Timeline SHALL NOT change because of this rule.

#### Scenario: Last 30 days with the default threshold

- **WHEN** the ghosted threshold is 30 days and the admin selects Last 30 days
- **THEN** the Ghosted card is greyed out, shows "—" and states why

#### Scenario: Switching to a longer preset

- **WHEN** the ghosted threshold is 30 days and the admin switches from Last 30 days to Last 90 days
- **THEN** the Ghosted card returns to its normal appearance and shows a percentage, without a full page reload

#### Scenario: All time

- **WHEN** the admin views All time
- **THEN** the Ghosted card shows its normal appearance and a percentage

#### Scenario: Threshold longer than the preset

- **WHEN** the ghosted threshold is 60 days and the admin selects Last 30 days
- **THEN** the Ghosted card is greyed out

#### Scenario: Threshold shorter than the preset

- **WHEN** the ghosted threshold is 14 days and the admin selects Last 30 days
- **THEN** the Ghosted card shows its normal appearance and a percentage

#### Scenario: Custom range starting inside the threshold

- **WHEN** the admin sets a from date that is exactly the threshold number of days before today, or later
- **THEN** the Ghosted card is greyed out

#### Scenario: Custom range starting before the threshold

- **WHEN** the admin sets a from date one day earlier than the threshold number of days before today
- **THEN** the Ghosted card shows its normal appearance and a percentage, including when the to date is recent

#### Scenario: Custom range with no start date

- **WHEN** the admin sets only a to date
- **THEN** the Ghosted card shows its normal appearance

#### Scenario: Older period with no applications

- **WHEN** the selected period starts before the threshold and contains no applications
- **THEN** the Ghosted card shows "—" like the other rate cards, with its normal appearance and no reason text

#### Scenario: Application on the period's first day already past the threshold

- **WHEN** a period is too recent for a ghosted rate, and an application applied to on its first day has been silent for longer than the threshold
- **THEN** the Ghosted card is still greyed out
- **AND** the Outcomes chart and the Timeline still show that application as ghosted
