# resume-revision-diff

## Purpose

Shows a reviewer exactly what an AI-drafted resume revision changes, compared with a baseline they choose, so a draft can be approved or rejected without re-reading the whole resume.

## Requirements

### Requirement: A draft revision is compared with a selectable baseline

When a user with `edit-resume` views a draft revision on the resume review page, the system SHALL compare the revision's data with a baseline and show the differences. The baseline SHALL be one of:
- **base** (the default): the resume version the revision branched from;
- **live**: the current resume version;
- **previous**: the nearest lower-numbered revision that still exists for the same base version.

The chosen baseline SHALL be reflected in the page URL. The page SHALL offer **live** only when the revision's base version is no longer the live version, and **previous** only when such a revision exists. A requested baseline that is unknown or not available SHALL fall back to **base**, and the page SHALL say that it did. The page SHALL always state which baseline is in use. The plain-text and JSON responses for a revision SHALL be unchanged.

#### Scenario: Default baseline

- **WHEN** a user with `edit-resume` opens a revision without choosing a baseline
- **THEN** the revision is compared with the version it branched from, and the page says so

#### Scenario: Comparing with the live resume

- **WHEN** a revision's base version is no longer live and the reviewer selects the live baseline
- **THEN** the revision is compared with the current resume version and the URL records the live baseline

#### Scenario: Live baseline not offered for a current draft

- **WHEN** a revision's base version is the live version
- **THEN** the live baseline is not offered

#### Scenario: Comparing with the previous revision

- **WHEN** revisions 1 and 2 are pending for the same base version and the reviewer selects the previous baseline on revision 2
- **THEN** revision 2 is compared with revision 1's data, so only the edits made after revision 1 are shown

#### Scenario: No previous revision

- **WHEN** a revision has no earlier revision for its base version
- **THEN** the previous baseline is not offered

#### Scenario: Unavailable baseline requested

- **WHEN** the URL asks for the previous baseline on a revision that has none, or for an unknown baseline
- **THEN** the revision is compared with its base version and the page says the requested comparison was not available

#### Scenario: Unauthorized viewer

- **WHEN** a user without `edit-resume` requests a revision with any baseline
- **THEN** the request is denied as it is today, and no diff is computed

### Requirement: Differences are identified per section, entry, field and word

The comparison SHALL cover personal information, skills, experience, education and projects.
- **Entries** (jobs, projects, education entries, skill categories) SHALL be matched by identity rather than by position. Each SHALL be reported as added, removed, modified or moved. An entry whose position changed relative to the others SHALL be reported as moved, along with any field changes.
- **Bullets** within an entry SHALL be aligned so that an unchanged bullet is never reported as changed. A reworded bullet SHALL be reported as one modified bullet with its inserted and deleted words, not as a removal and an addition.
- **Skills** within a category SHALL be reported as added or removed, ignoring letter case. A change only in their order SHALL be reported as an order change.
- **Text fields** that changed SHALL be reported with their inserted and deleted words.

Values that are absent, null or empty SHALL be treated as equal. A revision whose data is malformed (for example a section sent as a single entry instead of a list) SHALL be compared in the same tolerant way the page renders it, never causing an error.

#### Scenario: Reworded bullet

- **WHEN** a job's bullet "Led a team of 4 engineers" becomes "Led a team of 6 engineers"
- **THEN** one modified bullet is reported, with "4" deleted and "6" inserted

#### Scenario: Bullet added among unchanged bullets

- **WHEN** a new bullet is inserted between two unchanged bullets of a job
- **THEN** exactly one added bullet is reported and the other two are unchanged

#### Scenario: Job added and job removed

- **WHEN** the revision adds a job at a new company and removes an old one
- **THEN** the new job is reported as added and the old one as removed

#### Scenario: Jobs reordered

- **WHEN** the revision swaps the order of two otherwise unchanged jobs
- **THEN** they are reported as moved and no field changes are reported for them

#### Scenario: Skills changed

- **WHEN** a skill category gains "Rust", loses "Perl", and keeps "Go" written as "go"
- **THEN** "Rust" is reported added, "Perl" removed, and "Go" unchanged

#### Scenario: No differences

- **WHEN** the revision's data equals the baseline's
- **THEN** no changes are reported

#### Scenario: Malformed revision

- **WHEN** the revision's experience section is a single job object instead of a list
- **THEN** the comparison treats it as a one-job list and the page renders without error

### Requirement: Changes to fields the resume does not show are reported

The comparison SHALL report changes to fields that the rendered resume does not display. These are a job's salary, title label and freelance flag, and the email, phone, LinkedIn and website in personal information. Each such change SHALL be labelled as not shown on the resume.

#### Scenario: Salary edited

- **WHEN** the revision changes a job's starting salary
- **THEN** the Changes panel lists the salary change with its before and after values, labelled as not shown on the resume

#### Scenario: Contact detail edited

- **WHEN** the revision changes the email address
- **THEN** the Changes panel lists it, labelled as not shown on the resume

### Requirement: A Changes panel lists every difference

The review page SHALL show a Changes panel above the resume for any viewed revision. The panel SHALL show the number of changes and the baseline in use, and SHALL group the changes by section. Each change SHALL name the entry and field it concerns, say whether it was added, removed, modified or moved, and show the value before and after. A modified text value SHALL highlight its inserted and deleted words. When there are no differences, the panel SHALL say the revision matches the baseline. Inserted and deleted text SHALL be distinguishable without relying on colour alone.

#### Scenario: Panel content

- **WHEN** a revision rewords one bullet and adds one skill
- **THEN** the panel shows 2 changes, under Experience and Skills, each naming its entry and showing before and after

#### Scenario: Identical revision

- **WHEN** the revision matches the baseline
- **THEN** the panel says it matches the baseline and shows no change list

#### Scenario: Text-only distinction

- **WHEN** the page is viewed without colour
- **THEN** deleted text is still struck through and inserted text is still underlined

### Requirement: The rendered resume shows tracked changes with a toggle

The review page SHALL render the revision with its differences from the baseline marked in place:
- inserted words and added entries, bullets and skills SHALL be marked as insertions;
- deleted words and removed entries, bullets and skills SHALL be shown in place, marked as deletions;
- moved entries SHALL be tagged as moved.

A "Show changes" control SHALL switch between this marked-up rendering and the clean rendering of the revision, which matches what the page shows today. The control SHALL default to showing changes, and SHALL be remembered in the reviewer's browser when browser storage is available. The page SHALL work when it is not. The Changes panel, the approve and reject actions, and the stale warning SHALL be unaffected by the control. The live resume page without a revision SHALL be unchanged.

#### Scenario: Marked-up rendering

- **WHEN** the reviewer opens a revision that rewords a bullet and removes a project
- **THEN** the bullet shows its deleted and inserted words, and the removed project appears struck through where it was

#### Scenario: Clean rendering

- **WHEN** the reviewer turns Show changes off
- **THEN** the resume shows the revision without deletions or change marks, as it renders today, and the Changes panel is still shown

#### Scenario: Remembered choice

- **WHEN** the reviewer turns Show changes off and later opens another revision in the same browser
- **THEN** that revision opens with Show changes off

#### Scenario: Public resume unaffected

- **WHEN** any visitor opens `/resume` without a revision
- **THEN** the page renders exactly as before, with no Changes panel or toggle
