# application-follow-ups Specification

## Purpose

Lets an application keep the email addresses of the people involved in the hiring process, and drafts a weekly follow-up to them when an application has gone quiet, up to a limited number per status stage and not while an interview is in progress. Nothing is sent until the site owner approves the draft.

## Requirements

### Requirement: Application contacts

An Application SHALL hold zero or more contacts. Each contact SHALL have an email address and MAY have a name, a role and notes. A contact's email address SHALL be a valid address and SHALL be unique within its application, ignoring case. The admin SHALL be able to add, edit and remove contacts from the application's Discussion page. Removing a contact SHALL NOT change follow-ups already sent to it.

#### Scenario: Adding a recruiter

- **WHEN** the admin adds a contact with email `pat@example.com`, name "Pat Lee" and role "Recruiter" to an application
- **THEN** the application lists that contact with its name, role and email

#### Scenario: Duplicate address refused

- **WHEN** the admin adds `Pat@Example.com` to an application that already has the contact `pat@example.com`
- **THEN** the request fails validation and no contact is added

#### Scenario: Invalid address refused

- **WHEN** the admin adds a contact whose email is `not-an-email`
- **THEN** the request fails validation and no contact is added

#### Scenario: Same address on two applications

- **WHEN** the admin adds `pat@example.com` to two different applications
- **THEN** both applications hold the contact

#### Scenario: Removing a contact

- **WHEN** the admin removes a contact who was a recipient of a sent follow-up
- **THEN** the contact no longer appears on the application
- **AND** the sent follow-up still lists that address among its recipients

#### Scenario: Contact from another application

- **WHEN** a request edits or removes a contact through an application it does not belong to
- **THEN** the request is refused as not found

### Requirement: Follow-ups are switched on per application

Each Application SHALL have a follow-up switch, off by default. Follow-ups SHALL be drafted only for an application whose switch is on, and every contact of that application SHALL be a recipient of its follow-ups. Turning the switch off SHALL discard any pending draft.

#### Scenario: New application

- **WHEN** an application is created
- **THEN** its follow-up switch is off

#### Scenario: Turning follow-ups off

- **WHEN** the admin turns the switch off on an application that has a pending draft
- **THEN** the draft is discarded and no further drafts are made for that application

### Requirement: When a follow-up is due

An application SHALL be due for a follow-up when all of the following hold:

- it is not deleted;
- its follow-up switch is on;
- its status is `applied`, `interviewed` or `offered` (not `interviewing`, while an interview is in progress, not `ghosted`, and not a terminal status);
- it has at least one contact;
- it has no pending draft;
- fewer than the configured maximum (3 by default) follow-ups have been sent since its latest status-history entry was recorded;
- at least the configured cadence (7 days by default) has passed since the later of its latest status-history entry's date and the time its last follow-up was sent.

The maximum SHALL be a single site-wide setting. Its count SHALL start again whenever a new status-history entry is recorded, so each status stage gets up to the maximum. A scheduled run SHALL check for due applications at least once a day and SHALL request one draft for each.

#### Scenario: A week after applying

- **WHEN** an application with follow-ups on and one contact has had `applied` as its only history entry for 7 days
- **THEN** the next scheduled run requests a draft for it

#### Scenario: Not yet a week

- **WHEN** an application's latest status-history entry is 6 days old and no follow-up has been sent
- **THEN** no draft is requested

#### Scenario: Weekly after a sent follow-up

- **WHEN** a follow-up was sent 7 days ago and no status-history entry has been added since
- **THEN** the next scheduled run requests another draft

#### Scenario: Paused while interviewing

- **WHEN** an application with follow-ups on is `interviewing` and its latest entry is 10 days old
- **THEN** no draft is requested

#### Scenario: Resumes after the interview

- **WHEN** an `interviewing` application gets an `interviewed` entry and 7 days pass with no further entry
- **THEN** the next scheduled run requests a draft for it

#### Scenario: Maximum reached

- **WHEN** an `applied` application has had 3 follow-ups sent since its `applied` entry, the maximum is 3, and a week has passed since the last one
- **THEN** no draft is requested

#### Scenario: Maximum resets on a status change

- **WHEN** an application that reached the maximum while `applied` gets an `interviewed` entry and 7 days pass
- **THEN** a draft is requested, and up to the maximum may be sent while it stays `interviewed`

#### Scenario: Discarded drafts do not count

- **WHEN** an application has had 3 drafts discarded and none sent since its latest entry
- **THEN** it can still become due

#### Scenario: Ghosted application

- **WHEN** a `ghosted` application with follow-ups on and a contact has had no entry for longer than the cadence
- **THEN** no draft is requested, because the company has already left the earlier follow-ups unanswered

#### Scenario: Not in a follow-up status

- **WHEN** an application is `draft`, `passed`, `interviewing`, `accepted`, `hired`, `rejected` or `withdrawn`
- **THEN** no draft is requested regardless of its dates

#### Scenario: No contacts

- **WHEN** an application with follow-ups on has no contacts
- **THEN** no draft is requested

#### Scenario: Draft already waiting

- **WHEN** a due application already has a pending draft
- **THEN** no second draft is requested

### Requirement: Drafting a follow-up

A follow-up draft SHALL be written by the AI system that is the `application-follow-up` feature default, using the built-in Application Follow-up system prompt. The draft SHALL be based on:

- the company, position and job description;
- the status history with its dates and notes;
- the contacts' names and roles;
- the subjects, bodies and dates of the follow-ups already sent.

The draft SHALL consist of a subject and a plain-text body, and SHALL be stored as pending on the application. Drafting SHALL NOT send any email to a contact. When no active `application-follow-up` default exists, or drafting fails, no draft SHALL be stored, the failure SHALL be logged, and the application SHALL be retried on a later scheduled run.

#### Scenario: Draft stored

- **WHEN** a draft is requested for a due application and the AI system replies with a subject and body
- **THEN** a pending follow-up with that subject and body is stored on the application
- **AND** no email is sent to any contact

#### Scenario: No follow-up system configured

- **WHEN** a draft is requested and no AI system is the `application-follow-up` default
- **THEN** no draft is stored
- **AND** the application's Discussion page states that no follow-up AI system is configured

#### Scenario: AI failure

- **WHEN** the AI system errors while drafting
- **THEN** no draft is stored and the application is due again on the next scheduled run

#### Scenario: Reply without a subject

- **WHEN** the AI system replies with a body but no subject line
- **THEN** the draft is stored with a default subject naming the position and company

### Requirement: Application Follow-up system prompt and feature default

The system SHALL provide a built-in Application Follow-up system prompt at a fixed id within the reserved built-in range, seeded with starter content and editable on the System Prompts page. Seeding SHALL NOT overwrite an existing prompt that already holds that id. The AI system form SHALL offer `application-follow-up` as a chat feature default, and at most one AI system SHALL be the `application-follow-up` default at a time.

#### Scenario: Prompt exists after deployment

- **WHEN** migrations have run
- **THEN** the System Prompts page lists the Application Follow-up prompt with editable starter content

#### Scenario: Fixed id already taken

- **WHEN** the seed runs where a different prompt already holds the Application Follow-up prompt's id
- **THEN** the seed stops with an error naming that prompt and the existing prompt is unchanged

#### Scenario: Assigning the default

- **WHEN** the admin saves a chat AI system with the `application-follow-up` feature default checked
- **THEN** that system becomes the `application-follow-up` default and no other system remains one

### Requirement: The owner is told when a draft is ready

Storing a new pending draft SHALL send the site owner a notification email naming the company and position and linking to the application's Discussion page. The notification SHALL be queued, SHALL go only to the configured owner address, and SHALL NOT contain the contacts' email addresses. The Applications list SHALL mark each application that has a pending draft.

#### Scenario: Notification sent

- **WHEN** a pending draft is stored for "Staff Engineer" at "Acme"
- **THEN** a notification addressed to the owner is queued that names Acme and Staff Engineer and links to that application

#### Scenario: List marker

- **WHEN** the Applications list renders an application with a pending draft
- **THEN** its row shows that a follow-up is ready for review

### Requirement: Reviewing and sending a follow-up

The Discussion page SHALL show the application's contacts, its follow-up switch, how many follow-ups have been sent since the latest status change out of the maximum, whether follow-ups are paused or capped, its pending draft (if any) and its sent and failed follow-ups. The admin SHALL be able to edit a pending draft's subject and body, discard it, or approve it. Approving SHALL require a non-empty subject and body and at least one contact, SHALL record the application's current contacts as the recipients, and SHALL send the follow-up off the request path:

- From the site's configured mail address.
- To every recipient.
- An open copy to the configured copy address (`MAIL_CC_ADDRESS`), when one is set, so a reply-all reaches a real inbox even when the from address is a no-reply one.
- A blind copy to the owner, unless the owner is already the open copy.
- The body as plain text, with no site branding.

A follow-up SHALL be sent at most once. A send that fails SHALL be marked failed with its reason and SHALL be retryable by approving it again. Discarded, sent and failed follow-ups SHALL NOT be editable.

#### Scenario: Cap shown

- **WHEN** the admin opens an `applied` application that has had 3 follow-ups sent since its `applied` entry and the maximum is 3
- **THEN** the page shows 3 of 3 sent and that no more will be drafted until the status changes

#### Scenario: Editing a draft

- **WHEN** the admin changes a pending draft's body and saves
- **THEN** the draft keeps the new body and is still pending

#### Scenario: Approve and send

- **WHEN** the admin approves a pending draft on an application with contacts `pat@example.com` and `sam@example.com`
- **THEN** one email with the draft's subject and body is sent to both addresses with the owner blind-copied
- **AND** the follow-up is marked sent with those recipients and the send time, and appears in the follow-up history

#### Scenario: Copy address configured

- **WHEN** the from address is `noreply@jasonvertucio.com`, the copy address is `me@jasonvertucio.com`, and the admin approves a pending draft
- **THEN** the email is sent from `noreply@jasonvertucio.com` to the recipients with `me@jasonvertucio.com` openly copied
- **AND** the owner is not also blind-copied when the owner address is that same copy address

#### Scenario: Approving twice

- **WHEN** a second approve request arrives for a follow-up that is already sent
- **THEN** the request is refused and no second email is sent

#### Scenario: Approving with no contacts

- **WHEN** the admin approves a pending draft after removing every contact
- **THEN** the request is refused and the draft stays pending

#### Scenario: Send failure

- **WHEN** the mail transport fails while sending an approved follow-up
- **THEN** the follow-up is marked failed with the reason
- **AND** approving it again retries the send

#### Scenario: Discarding

- **WHEN** the admin discards a pending draft
- **THEN** it is no longer pending, no email is sent, and the application can become due again on the usual schedule

#### Scenario: Draft from another application

- **WHEN** a request edits, approves or discards a follow-up through an application it does not belong to
- **THEN** the request is refused as not found

### Requirement: Follow-ups stop with the application

Adding a status-history entry to an application SHALL discard its pending draft, because the draft no longer reflects the application. An application that reaches a terminal status SHALL receive no further drafts. Deleting an application SHALL stop its follow-ups.

#### Scenario: Status changes while a draft waits

- **WHEN** the admin adds an `interviewed` entry to an application that has a pending draft
- **THEN** the draft is discarded and the application is next due 7 days after the new entry's date

#### Scenario: Interview scheduled while a draft waits

- **WHEN** the admin adds an `interviewing` entry to an application that has a pending draft
- **THEN** the draft is discarded and no draft is requested while it stays `interviewing`

#### Scenario: Rejected

- **WHEN** a `rejected` entry is added to an application with follow-ups on
- **THEN** any pending draft is discarded and no further drafts are requested

#### Scenario: Deleted application

- **WHEN** an application with follow-ups on is deleted
- **THEN** no further drafts are requested for it

### Requirement: Contacts and follow-ups require resume-editing permission

Every contact and follow-up page element and endpoint SHALL require an authenticated user holding the `edit-resume` permission. Contacts and follow-ups SHALL NOT be exposed through the public MCP server or the targeted-resume session's tools.

#### Scenario: Unauthorized user

- **WHEN** a signed-in user without `edit-resume` requests any contact or follow-up endpoint
- **THEN** the request is refused with 403

#### Scenario: Not in the session tools

- **WHEN** the application's AI session loads the job through its tools
- **THEN** the result contains no contact email addresses
