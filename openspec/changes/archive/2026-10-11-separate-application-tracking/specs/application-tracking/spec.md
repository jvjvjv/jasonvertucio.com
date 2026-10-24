## Purpose

Tracks every job the site owner considers or applies to as an Application, independently of whether a targeted resume was built for it, so that applying with the main resume alone is a first-class workflow.

## ADDED Requirements

### Requirement: An Application records a tracked job

The system SHALL represent each tracked job as an Application holding the job details (company, position, location, description, job URL), the fit assessment (score and summary), a status, the resume version used, an optional targeted resume, and an optional AI session. A targeted resume SHALL belong to at most one Application, and an AI session SHALL belong to at most one Application.

#### Scenario: Application without a targeted resume

- **WHEN** an application is recorded for a job applied to with the main resume
- **THEN** the Application exists with a resume version and no targeted resume and no AI session

#### Scenario: Application with a targeted resume

- **WHEN** a targeted resume is finalized in an application's AI session
- **THEN** that Application references the targeted resume
- **AND** the job details, fit assessment and status remain on the Application, not on the targeted resume

### Requirement: Starting a new session

The system SHALL provide a New Session page, reachable from a `+ New Session` button on both the Applications list and the Targeted Resumes list, where the admin enters or parses a job. Parsing a job URL successfully SHALL autofill the title, location, company and description fields. The action row SHALL offer "Begin Analysis" and "I Applied". Both actions SHALL require a job description.

#### Scenario: URL parse autofills the form

- **WHEN** the admin submits a job URL that parses successfully
- **THEN** the title, location, company and description fields are filled from the parse result and remain editable

#### Scenario: Begin Analysis

- **WHEN** the admin clicks "Begin Analysis" with a valid job
- **THEN** an Application is created in `draft` status with an AI session attached
- **AND** the admin lands on that application's Discussion page with the analysis starting automatically

#### Scenario: I Applied

- **WHEN** the admin clicks "I Applied" with a valid job
- **THEN** the system asks the admin to confirm which resume version was used, defaulting to the current version
- **AND** on confirmation an Application is created with that resume version, no AI session, status `applied`, and an `applied` status-history entry dated now
- **AND** the admin lands on that application's Discussion page

#### Scenario: I Applied cancelled

- **WHEN** the admin clicks "I Applied" and dismisses the confirmation
- **THEN** no Application is created and the form keeps its contents

#### Scenario: Missing job description

- **WHEN** the admin clicks either action without a job description
- **THEN** no Application is created and a validation error is shown

### Requirement: Application statuses

An Application's status SHALL be one of `draft`, `passed`, `applied`, `interviewing`, `interviewed`, `offered`, `accepted`, `hired`, `rejected`. `accepted`, `hired` and `rejected` are terminal. An application in `applied` status whose latest status-history entry is older than the configured ghosted threshold SHALL be displayed as "ghosted" without changing its stored status. Having a targeted resume SHALL NOT be a status.

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

### Requirement: Marking an application applied confirms the resume used

Marking an existing application as applied SHALL confirm which resume was used before recording it. If the application has a targeted resume, the confirmation SHALL state that the targeted resume was used. Otherwise the admin SHALL choose a main resume version, defaulting to the current one, and that version SHALL be recorded on the Application.

#### Scenario: Applied with the targeted resume

- **WHEN** the admin marks an application that has a targeted resume as applied and confirms
- **THEN** the application becomes `applied` and still references its targeted resume

#### Scenario: Applied with the main resume after an analysis

- **WHEN** the admin marks an application that has an AI session but no targeted resume as applied, and confirms a resume version
- **THEN** the application becomes `applied` with that resume version and no targeted resume
- **AND** no placeholder targeted resume is created

### Requirement: Application status history

The system SHALL keep a dated, annotated history of pipeline statuses (`applied` onward) per Application. Adding an entry SHALL set the application's status to the entry's status. Entries SHALL NOT be addable to an application in a terminal status. An entry's notes and date SHALL be editable. Deleting an entry SHALL set the status to the latest remaining entry's status, or to `draft` when none remain.

#### Scenario: Adding a status entry

- **WHEN** the admin adds an `interviewing` entry to an `applied` application
- **THEN** the entry is stored with its notes and date and the application's status becomes `interviewing`

#### Scenario: Terminal application

- **WHEN** the admin attempts to add an entry to a `rejected` application
- **THEN** the request is refused and nothing is stored

#### Scenario: Deleting the only entry

- **WHEN** the admin deletes an application's only status-history entry
- **THEN** the application's status becomes `draft`

#### Scenario: Entry from another application

- **WHEN** a request edits or deletes a status entry through an application it does not belong to
- **THEN** the request is refused as not found

### Requirement: Applications list

The system SHALL provide an Applications list page showing every non-deleted Application, most recently active first, with columns for company and position, resume, base resume version, fit score, AI usage, status and last update. The Resume column SHALL show an em dash when the application has no targeted resume, and an edit button with the EditNote icon linking to the Edit Targeted Resume page when it has one.

#### Scenario: Application without a targeted resume

- **WHEN** the list renders an application with no targeted resume
- **THEN** its Resume cell shows an em dash

#### Scenario: Application with a targeted resume

- **WHEN** the list renders an application with a targeted resume
- **THEN** its Resume cell shows an EditNote button that opens the Edit Targeted Resume page for that resume

#### Scenario: Application with no AI session

- **WHEN** the list renders an application created via "I Applied"
- **THEN** the row appears with its status and resume version, and its AI usage cell is empty

### Requirement: Applications list filters

The Applications list SHALL keep its search field visible and place all other filters in a dialog opened from a Filter button. The dialog SHALL offer a multi-select status filter and a "Uses targeted resume" filter with the choices Any, Yes and No. The Filter button SHALL indicate how many filters are active. Applied filters SHALL be reflected in the page URL so a filtered view survives reload. Search SHALL match company, position, and the text of the application's AI session messages.

#### Scenario: Filtering to targeted-resume applications

- **WHEN** the admin sets "Uses targeted resume" to Yes and applies
- **THEN** only applications with a targeted resume are listed

#### Scenario: Filtering to main-resume applications

- **WHEN** the admin sets "Uses targeted resume" to No and applies
- **THEN** only applications without a targeted resume are listed

#### Scenario: Filtering by status

- **WHEN** the admin selects `applied` and `interviewing` and applies
- **THEN** only applications in those statuses are listed

#### Scenario: Active filter indicator

- **WHEN** two filters are active
- **THEN** the Filter button shows that two filters are applied

#### Scenario: Clearing filters

- **WHEN** the admin clears filters in the dialog
- **THEN** all applications are listed and the URL carries no filter parameters

### Requirement: Application Discussion page

The system SHALL provide a Discussion page per Application showing the chat, the job details and fit assessment, the status history, the Resume card and the cover letter. Editing job details there SHALL update the Application. The page SHALL offer Mark Applied, Pass and (when a job URL exists) Job URL actions. The page SHALL NOT carry a manual-edit tab; editing the targeted resume is reached through the Edit button inside the Resume card.

#### Scenario: Application with an AI session

- **WHEN** the admin opens an application that has an AI session
- **THEN** the chat is shown and behaves as the targeted resume builder chat did, including finalizing a resume and a cover letter

#### Scenario: Application without an AI session

- **WHEN** the admin opens an application created via "I Applied"
- **THEN** the chat area offers "Begin Analysis" instead of a transcript
- **AND** the job details, status history and cover letter areas are available

#### Scenario: Beginning analysis later

- **WHEN** the admin clicks "Begin Analysis" on an application without an AI session
- **THEN** an AI session is attached to that same Application and the analysis starts
- **AND** the application's status and status history are unchanged

#### Scenario: Resume card with a targeted resume

- **WHEN** the application has a targeted resume
- **THEN** the Resume card shows its download actions and an Edit button linking to the Edit Targeted Resume page

#### Scenario: Resume card without a targeted resume

- **WHEN** the application has no targeted resume
- **THEN** the Resume card shows the main resume version recorded for the application and no Edit button

### Requirement: Finalizing a targeted resume attaches it to the Application

Finalizing a resume in an application's AI session SHALL create or update that application's targeted resume and SHALL NOT change the application's status.

#### Scenario: First finalize

- **WHEN** a resume is finalized in a `draft` application's session
- **THEN** a targeted resume is created and attached to the application
- **AND** the application's status is still `draft`

#### Scenario: Finalize after applying

- **WHEN** a resume is finalized again in an `applied` application's session
- **THEN** the existing targeted resume's content is replaced and the status is still `applied`

### Requirement: Discarding a targeted resume

An application's targeted resume SHALL always be the resume used for that application; the system SHALL NOT offer a way to keep a targeted resume while recording the main resume as the one sent. Instead the admin SHALL be able to discard a targeted resume, after explicit confirmation, from the Resume card, the Edit Targeted Resume page and the Targeted Resumes list. Discarding SHALL permanently delete the targeted resume and its rendered documents, leave the application with no targeted resume and its recorded main resume version, and leave the application's status, status history, AI session and cover letter unchanged. Discarding SHALL be refused once the application has an `applied` status-history entry, because the targeted resume is then the record of what was sent. The discard SHALL be recorded in the application's AI session so the agent no longer assumes the resume exists.

#### Scenario: Discarding before applying

- **WHEN** the admin discards the targeted resume of a `draft` application and confirms
- **THEN** the targeted resume and its rendered DOCX and PDF no longer exist
- **AND** the application has no targeted resume, is still `draft`, and shows an em dash in the Applications list Resume column
- **AND** it no longer appears in the Targeted Resumes list

#### Scenario: Applying after discarding

- **WHEN** the admin marks that application as applied
- **THEN** the confirmation asks for a main resume version, as for any application without a targeted resume

#### Scenario: Discard cancelled

- **WHEN** the admin dismisses the confirmation
- **THEN** nothing is deleted

#### Scenario: Discard refused after applying

- **WHEN** a discard is requested for the targeted resume of an application that has an `applied` status-history entry
- **THEN** the request is refused, nothing is deleted, and the discard action is not offered in the interface

#### Scenario: Agent is told

- **WHEN** a targeted resume is discarded for an application with an AI session
- **THEN** the session history gains a message stating the resume was discarded, tagged `metadata.origin = "resume_discarded"`, without triggering an agent turn

#### Scenario: Building a new one afterwards

- **WHEN** a resume is finalized again in the session after a discard
- **THEN** a new targeted resume is created and attached to the application

### Requirement: Cover letters attach to the Application

A cover letter created for a job SHALL be attached to that job's Application, not to a targeted resume. Creating a cover letter in an application's AI session SHALL NOT require a targeted resume to exist. An application SHALL have at most one session-generated cover letter, replaced on re-finalize. Cover letters created without a job SHALL remain unattached.

#### Scenario: Cover letter for a main-resume application

- **WHEN** a cover letter is finalized in the session of an application that has no targeted resume
- **THEN** the cover letter is saved and attached to the application

#### Scenario: Re-finalizing a cover letter

- **WHEN** a cover letter is finalized a second time for the same application
- **THEN** the application's existing cover letter is updated rather than a second one created

#### Scenario: Standalone cover letter

- **WHEN** a cover letter is created from the Cover Letters page with no application chosen
- **THEN** it is saved with no application

### Requirement: Deleting an application

Deleting an Application SHALL remove it from the lists and from metrics while leaving it recoverable in the database, and SHALL hide its AI session the same way.

#### Scenario: Deleted application

- **WHEN** the admin deletes an application and confirms
- **THEN** it no longer appears in the Applications list, the Targeted Resumes list or Application Metrics

### Requirement: Application pages and endpoints require resume-editing permission

Every Application page and endpoint SHALL require an authenticated user holding the `edit-resume` permission.

#### Scenario: Unauthorized user

- **WHEN** a signed-in user without `edit-resume` requests the Applications list or any application endpoint
- **THEN** the request is refused with 403

#### Scenario: Anonymous visitor

- **WHEN** an unauthenticated visitor requests the Applications list
- **THEN** they are redirected to login

### Requirement: Previous builder URLs redirect

Requests to the former Targeted Resume Builder pages SHALL redirect to their Application equivalents.

#### Scenario: Old list URL

- **WHEN** the admin opens `/admin/resume/targeted-builder`
- **THEN** they are redirected to the Applications list

#### Scenario: Old session URL

- **WHEN** the admin opens `/admin/resume/targeted-builder/{conversation}` for a session that has an Application
- **THEN** they are redirected to that application's Discussion page

### Requirement: Existing data is migrated without loss

Migrating to Applications SHALL preserve every existing tracked job: each existing targeted resume and each existing targeted-resume AI session SHALL yield exactly one Application, with its job details, fit assessment, status history, cover letters and tailored content intact.

#### Scenario: Finalized targeted resume

- **WHEN** a targeted resume with tailored content and status `finalized` is migrated
- **THEN** an Application in `draft` status references it, carrying its company, position, description, job URL, fit score and summary
- **AND** the targeted resume keeps its own title and tailored content

#### Scenario: Applied targeted resume

- **WHEN** a targeted resume with status `applied` and status-history entries is migrated
- **THEN** its Application has status `applied` and the same history entries with the same dates and notes

#### Scenario: Main-resume placeholder

- **WHEN** a targeted resume flagged as a base resume with no tailored content is migrated
- **THEN** its Application has no targeted resume, keeps the recorded resume version, status and history
- **AND** the placeholder targeted resume no longer exists

#### Scenario: Session that never produced a resume

- **WHEN** a targeted-resume AI session with no targeted resume is migrated
- **THEN** an Application is created from the session's recorded company, job title, description, job URL and fit assessment, in `passed` status if the session was passed and `draft` otherwise

#### Scenario: Passed session with an unapplied resume

- **WHEN** a session marked passed has a targeted resume in `draft` or `finalized` status
- **THEN** its Application has status `passed`

#### Scenario: Deleted session

- **WHEN** a previously deleted targeted-resume session is migrated
- **THEN** its Application is created already deleted and appears in no list

#### Scenario: Cover letters follow their job

- **WHEN** a cover letter attached to a targeted resume is migrated
- **THEN** it is attached to that targeted resume's Application
