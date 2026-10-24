## MODIFIED Requirements

### Requirement: Starting a new session

The system SHALL provide a New Session page, reachable from a `+ New Session` button on both the Applications list and the Targeted Resumes list, where the admin enters or parses a job. Parsing a job URL successfully SHALL autofill the title, location, company and description fields. The action row SHALL offer "Begin Analysis" and "I Applied". Both actions SHALL require a job description. "I Applied" SHALL NOT require an AI system; when one is selected, it SHALL also start an AI session for a fit score only.

#### Scenario: URL parse autofills the form

- **WHEN** the admin submits a job URL that parses successfully
- **THEN** the title, location, company and description fields are filled from the parse result and remain editable

#### Scenario: Begin Analysis

- **WHEN** the admin clicks "Begin Analysis" with a valid job
- **THEN** an Application is created in `draft` status with an AI session attached
- **AND** the admin lands on that application's Discussion page with the analysis starting automatically

#### Scenario: I Applied

- **WHEN** the admin clicks "I Applied" with a valid job and no AI system selected
- **THEN** the system asks the admin to confirm which resume version was used, defaulting to the current version
- **AND** on confirmation an Application is created with that resume version, no AI session, status `applied`, and an `applied` status-history entry dated now
- **AND** the admin lands on that application's Discussion page

#### Scenario: I Applied with an AI system selected

- **WHEN** the admin clicks "I Applied" with a valid job and an AI system selected, and confirms the resume version
- **THEN** an Application is created with that resume version, status `applied` and an `applied` status-history entry dated now
- **AND** an AI session with the selected system is attached whose first user message begins "I have already applied to this. Please provide a fit score, but there is no need for a targeted resume or cover letter." followed by the job details
- **AND** the admin lands on that application's Discussion page with that session starting automatically

#### Scenario: I Applied cancelled

- **WHEN** the admin clicks "I Applied" and dismisses the confirmation
- **THEN** no Application is created and the form keeps its contents

#### Scenario: Missing job description

- **WHEN** the admin clicks either action without a job description
- **THEN** no Application is created and a validation error is shown

### Requirement: Applications list

The system SHALL provide an Applications list page showing every non-deleted Application, most recently active first, with columns for company and position, resume, base resume version, fit score, AI usage, intel, status, last update and actions. The Resume column SHALL show an em dash when the application has no targeted resume, and an edit button with the EditNote icon linking to the Edit Targeted Resume page when it has one. The Intel column SHALL link to the application's own intel page when it has Role intel; otherwise, when another non-deleted application's Role intel refers to a Company with the same name, it SHALL link to that intel page, marked as company-only; otherwise it SHALL show an em dash. The actions column SHALL order its icons from least to most persistent, left to right: Pass (only for `draft`), Begin intel (only when the application has no Role intel), Open, Delete.

#### Scenario: Application without a targeted resume

- **WHEN** the list renders an application with no targeted resume
- **THEN** its Resume cell shows an em dash

#### Scenario: Application with a targeted resume

- **WHEN** the list renders an application with a targeted resume
- **THEN** its Resume cell shows an EditNote button that opens the Edit Targeted Resume page for that resume

#### Scenario: Application with no AI session

- **WHEN** the list renders an application created via "I Applied" with no AI system selected
- **THEN** the row appears with its status and resume version, and its AI usage cell is empty

#### Scenario: Application with role intel

- **WHEN** the list renders an application that has Role intel
- **THEN** its Intel cell links to that intel page and shows the intel's state when it is not `ready`

#### Scenario: Company known from another application

- **WHEN** the list renders an application without Role intel whose company name matches the Company of another application's Role intel
- **THEN** its Intel cell links to that other intel page, labelled as intel on the company only
- **AND** its actions include Begin intel

#### Scenario: No intel at all

- **WHEN** the list renders an application without Role intel and no matching Company
- **THEN** its Intel cell shows an em dash and its actions include Begin intel

#### Scenario: Action order

- **WHEN** the list renders a `draft` application without Role intel
- **THEN** its actions appear left to right as Pass, Begin intel, Open, Delete

#### Scenario: Action order once applied with intel

- **WHEN** the list renders an `applied` application with Role intel
- **THEN** its actions appear left to right as Open, Delete

### Requirement: Application Discussion page

The system SHALL provide a Discussion page per Application showing the chat, the job details and fit assessment, the status history, the Resume card and the cover letter. Editing job details there SHALL update the Application. The page SHALL offer Mark Applied, Pass, Intel and (when a job URL exists) Job URL actions in its header. The Intel action SHALL open the application's intel page when it has Role intel, and otherwise SHALL start intel as described by the company-intel capability and open the intel page with the first turn streaming. The page SHALL NOT carry a manual-edit tab; editing the targeted resume is reached through the Edit button inside the Resume card.

#### Scenario: Application with an AI session

- **WHEN** the admin opens an application that has an AI session
- **THEN** the chat is shown and behaves as the targeted resume builder chat did, including finalizing a resume and a cover letter

#### Scenario: Application without an AI session

- **WHEN** the admin opens an application created via "I Applied" with no AI system selected
- **THEN** the chat area offers "Begin Analysis" instead of a transcript
- **AND** the job details, status history and cover letter areas are available

#### Scenario: Beginning analysis later

- **WHEN** the admin clicks "Begin Analysis" on a `draft` or `passed` application without an AI session
- **THEN** an AI session is attached to that same Application and the analysis starts with the full analysis message
- **AND** the application's status and status history are unchanged

#### Scenario: Beginning analysis on an applied application

- **WHEN** the admin clicks "Begin Analysis" on an application without an AI session whose status is `applied` or later
- **THEN** an AI session is attached and starts with the fit-score-only message "I have already applied to this. Please provide a fit score, but there is no need for a targeted resume or cover letter." followed by the job details
- **AND** the application's status and status history are unchanged

#### Scenario: Intel header item with intel

- **WHEN** the admin uses the Intel header item on an application with Role intel
- **THEN** the application's intel page opens

#### Scenario: Intel header item without intel

- **WHEN** the admin uses the Intel header item on an application without Role intel
- **THEN** intel is started for that application and its intel page opens with the first turn streaming

#### Scenario: Resume card with a targeted resume

- **WHEN** the application has a targeted resume
- **THEN** the Resume card shows its download actions and an Edit button linking to the Edit Targeted Resume page

#### Scenario: Resume card without a targeted resume

- **WHEN** the application has no targeted resume
- **THEN** the Resume card shows the main resume version recorded for the application and no Edit button
