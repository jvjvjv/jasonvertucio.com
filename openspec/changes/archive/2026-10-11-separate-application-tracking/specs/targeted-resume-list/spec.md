## Purpose

Lists the targeted resume documents themselves, apart from application tracking, so the site owner can find, download and edit a tailored resume without going through the job it was written for.

## ADDED Requirements

### Requirement: Targeted Resumes list page

The system SHALL provide a Targeted Resumes list page showing every targeted resume whose Application is not deleted, most recently edited first. Each row SHALL show the application's company and position, the resume's title, the base resume version, when it was last edited, DOCX and PDF download actions, an edit action using the EditNote icon linking to the Edit Targeted Resume page, and a link to its Application's Discussion page. Applications without a targeted resume SHALL NOT appear.

#### Scenario: Only applications with a targeted resume appear

- **WHEN** one application has a targeted resume and another was applied to with the main resume
- **THEN** the Targeted Resumes list shows only the first

#### Scenario: Opening the editor

- **WHEN** the admin clicks a row's edit action
- **THEN** the Edit Targeted Resume page opens for that resume

#### Scenario: Opening the application

- **WHEN** the admin clicks a row's application link
- **THEN** that application's Discussion page opens

#### Scenario: Downloading from the list

- **WHEN** the admin clicks a row's PDF download action
- **THEN** the targeted resume's PDF is downloaded, rendered on demand if no current rendering exists

#### Scenario: Empty list

- **WHEN** no targeted resumes exist
- **THEN** the page shows an empty state with the `+ New Session` action

### Requirement: Targeted Resumes list search

The Targeted Resumes list SHALL provide a search field matching the application's company and position and the resume's title.

#### Scenario: Searching by company

- **WHEN** the admin searches for a company name
- **THEN** only targeted resumes whose application's company matches are listed

### Requirement: Targeted Resumes list requires resume-editing permission

The Targeted Resumes list SHALL require an authenticated user holding the `edit-resume` permission.

#### Scenario: Unauthorized user

- **WHEN** a signed-in user without `edit-resume` requests the Targeted Resumes list
- **THEN** the request is refused with 403
