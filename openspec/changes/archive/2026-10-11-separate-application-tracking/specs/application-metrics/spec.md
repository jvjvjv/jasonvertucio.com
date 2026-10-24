## Purpose

Summarizes how the site owner's job applications are progressing — volume, funnel, outcomes and cycle times — over a chosen period.

## ADDED Requirements

### Requirement: Metrics count applied Applications

The Application Metrics dashboard SHALL count every non-deleted Application that has an `applied` status-history entry, whether it used a targeted resume or the main resume, dating each by its earliest `applied` entry.

#### Scenario: Main-resume application counted

- **WHEN** an application was applied to with the main resume
- **THEN** it is included in the KPIs, funnel, outcomes, over-time chart, cycle times and timeline

#### Scenario: Unapplied application excluded

- **WHEN** an application is in `draft` or `passed` status with no `applied` entry
- **THEN** it is not included in any metric

### Requirement: Date filtering

The dashboard SHALL offer the presets Last 30 days, Last 90 days, This year and All time, plus a custom from/to date range, defaulting to All time. The selected period SHALL restrict every section of the dashboard to applications whose applied date falls within it, inclusive of both end dates. The selected period SHALL be reflected in the page URL.

#### Scenario: Default view

- **WHEN** the admin opens the dashboard with no period chosen
- **THEN** All time is selected and every applied application is counted

#### Scenario: Preset selected

- **WHEN** the admin selects Last 30 days
- **THEN** every section counts only applications applied to within the last 30 days

#### Scenario: Custom range

- **WHEN** the admin sets a from date and a to date
- **THEN** every section counts only applications applied to on or between those dates
- **AND** no preset is shown as selected

#### Scenario: Open-ended custom range

- **WHEN** the admin sets only a from date
- **THEN** every section counts applications applied to on or after that date

#### Scenario: Invalid range

- **WHEN** the requested from date is after the to date, or either is not a date
- **THEN** the dashboard reports a validation error and does not show misleading figures

#### Scenario: Period with no applications

- **WHEN** no application was applied to within the selected period
- **THEN** the dashboard shows zero counts and empty states rather than an error

#### Scenario: Reload keeps the period

- **WHEN** the admin reloads the page after choosing a period
- **THEN** the same period is still selected

### Requirement: Timeline is collapsed by default

The dashboard SHALL present the Timeline inside an accordion that is collapsed when the page loads, and that expands to show the timeline for the selected period.

#### Scenario: Initial load

- **WHEN** the dashboard loads
- **THEN** the Timeline accordion is collapsed and the timeline is not visible

#### Scenario: Expanding the timeline

- **WHEN** the admin expands the Timeline accordion
- **THEN** the timeline is shown for the applications in the selected period
