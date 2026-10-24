## Purpose

Researches the hiring company and the role behind an application with an AI agent, and keeps what it learns as structured records: a Company shared by every application there, and Role intel belonging to one application.

## ADDED Requirements

### Requirement: Company records

The system SHALL keep a Company record per hiring company, holding its name and, when known, its website, headquarters, employee count, revenue, industry, year founded, ownership (public or private, with ticker when public) and a short summary. A Company SHALL be shared by every Role intel that refers to it. Two Companies SHALL NOT share the same website domain.

#### Scenario: Same company, second application

- **WHEN** intel is gathered for a second application at a company that already has a Company record with the same website domain
- **THEN** the second application's Role intel refers to the existing Company and no new Company is created

#### Scenario: Metadata is partial

- **WHEN** the agent records a Company whose revenue and employee count it could not find
- **THEN** the Company is stored with those fields empty, and they are shown as unknown

### Requirement: Company insights

The system SHALL keep Company insights: findings about a Company that bear on applying or interviewing there, each with a category (culture, values, mission, products, leadership, news, interview process, compensation, risks or other), a title, Markdown content, an optional source URL and the date it was recorded. Insights SHALL belong to exactly one Company.

#### Scenario: Insight with a source

- **WHEN** the agent records an insight citing a source URL
- **THEN** the insight is stored with that URL and the URL is shown as a link beside it

#### Scenario: Insights are shared

- **WHEN** a second application's Role intel refers to a Company that already has insights
- **THEN** that application's intel page shows the existing insights

### Requirement: Role intel

The system SHALL keep at most one Role intel per Application, holding a summary of the role and, when found, its responsibilities, requirements, team and reporting line, compensation, interview process and concerns, each as Markdown. Role intel SHALL refer to zero or one Company. Role intel SHALL record which AI session gathered it and its state: `queued`, `running`, `ready` or `failed`, with the failure reason when failed.

#### Scenario: One per application

- **WHEN** intel is requested for an application that already has Role intel
- **THEN** no second Role intel is created and the request is refused with 409

#### Scenario: Company not yet identified

- **WHEN** Role intel exists but the agent has not yet recorded a Company
- **THEN** the Role intel shows no company and its role findings are still shown

### Requirement: Company Intel system prompt

The system SHALL provide a Company Intel system prompt, seeded with starter content alongside the existing seeded prompts (Default, Creative, Technical, Targeted Resume and Cover Letter) and, like them, identified by a fixed id, so the admin can edit or rename it on the System Prompts page without breaking intel. Seeding SHALL NOT overwrite an existing prompt that already holds that id.

#### Scenario: Prompt exists after deployment

- **WHEN** the change is deployed and migrations have run
- **THEN** the System Prompts page lists the Company Intel prompt with editable starter content

#### Scenario: Fixed id already taken

- **WHEN** the seed runs where a different prompt already holds the Company Intel prompt's id
- **THEN** the seed stops with an error naming that prompt and the existing prompt is unchanged

### Requirement: Built-in prompt ids are reserved

Ids 1–49 of system prompts SHALL be reserved for built-in prompts; prompts the admin creates SHALL receive ids of 50 or more. Admin-made prompts that already hold an id in the reserved range when this is introduced SHALL be moved to ids of 50 or more, keeping their title, description and content, and every AI system assigned one SHALL remain assigned to it.

#### Scenario: New admin prompt

- **WHEN** the admin creates a system prompt
- **THEN** it receives an id of 50 or more

#### Scenario: Existing admin prompt in the reserved range

- **WHEN** the reservation runs while an admin-made prompt "Mock Interviewer" holds id 7 and an AI system is assigned it
- **THEN** "Mock Interviewer" exists at an id of 50 or more with the same content, the AI system is assigned to it, and id 7 is free

#### Scenario: Built-ins are not moved

- **WHEN** the reservation runs
- **THEN** the prompts with ids 1–5 keep their ids

#### Scenario: Renamed prompt

- **WHEN** the admin renames the Company Intel prompt
- **THEN** AI systems assigned that prompt are still offered for intel, and intel sessions still use its content

### Requirement: Company intel feature default

The AI system form SHALL offer `company-intel` as a feature default alongside `targeted-resume` and `cover-letter`. At most one AI system SHALL be the `company-intel` default at a time; making a system the default removes the default from any other.

#### Scenario: Assigning the default

- **WHEN** the admin saves an AI system with the `company-intel` feature default checked
- **THEN** that system becomes the `company-intel` default and no other system remains one

#### Scenario: Unknown feature refused

- **WHEN** a request saves an AI system with a feature default other than `targeted-resume`, `cover-letter` or `company-intel`
- **THEN** the request fails validation

### Requirement: Intel session

Gathering intel SHALL run in an AI session with the feature `company-intel`, attached to the Role intel. The session's instructions SHALL be the Company Intel prompt's content followed by the site's instructions for recording findings. Its first user message SHALL read "User has just applied to this job. Please begin an analysis of the role and company." followed by the job title, company name, location (when known) and job description. The agent SHALL record findings through tools that create or update the Company, add Company insights and update the Role intel; it SHALL also be able to use the web tools granted to its AI system. The session SHALL stay open for follow-up messages from the admin, whose answers may further update the records.

#### Scenario: First message

- **WHEN** an intel session starts for an application titled "Staff Engineer" at "Acme"
- **THEN** its first user message begins "User has just applied to this job. Please begin an analysis of the role and company." and contains "Staff Engineer", "Acme" and the job description

#### Scenario: Findings become records

- **WHEN** the agent's turn finishes after recording a company profile, three insights and a role summary
- **THEN** the Company, its three insights and the Role intel summary are stored and shown on the intel page

#### Scenario: Follow-up question

- **WHEN** the admin sends a follow-up message in an intel session
- **THEN** the agent answers in the same session, and any findings it records update the same Company and Role intel

#### Scenario: Web tools follow the system's grants

- **WHEN** the intel session's AI system has not been granted a web tool
- **THEN** the agent is not offered that tool in the session

### Requirement: Intel starts automatically when an application is marked applied

When an application gains an `applied` status entry, has no Role intel, and a `company-intel` default AI system exists, the system SHALL create its Role intel in the `queued` state and run the first intel turn in the background with the default system. When no default exists, the system SHALL NOT start intel automatically. Starting intel SHALL NOT delay, fail or roll back marking the application applied.

#### Scenario: Default exists

- **WHEN** the admin marks an application applied and a `company-intel` default exists
- **THEN** the application is marked applied, its Role intel is created as `queued`, and the first turn runs in the background, leaving the Role intel `ready` when it finishes

#### Scenario: No default

- **WHEN** the admin marks an application applied and no `company-intel` default exists
- **THEN** the application is marked applied and no Role intel is created

#### Scenario: I Applied on the New Session page

- **WHEN** the admin records a job with "I Applied" and a `company-intel` default exists
- **THEN** intel starts for the new application in the background

#### Scenario: Intel already exists

- **WHEN** an application that already has Role intel gains an `applied` entry
- **THEN** no new intel is started

#### Scenario: Background run fails

- **WHEN** the background intel turn fails or times out
- **THEN** the Role intel is `failed` with the reason, the application's status is unaffected, and the intel page offers Retry

### Requirement: Starting intel by hand

The admin SHALL be able to start intel for an application that has no Role intel, from the Applications list or the Discussion page. When a `company-intel` default exists it SHALL be used without asking. When none exists, the admin SHALL be asked to choose among the active AI systems assigned the Company Intel prompt; when there are none, the admin SHALL be told that an AI system with the Company Intel prompt is needed, with a link to AI Systems. A start from the Applications list SHALL run the first turn in the background. A start from the Discussion page SHALL open the intel page with the first turn streaming live.

#### Scenario: Start from the list with a default

- **WHEN** the admin clicks Begin intel on a list row and a default exists
- **THEN** Role intel is created as `queued`, the first turn runs in the background, and the row's Intel cell links to the new session

#### Scenario: Start without a default

- **WHEN** the admin clicks Begin intel and no default exists
- **THEN** a dialog lists the active AI systems assigned the Company Intel prompt, and choosing one starts intel with that system

#### Scenario: No eligible system

- **WHEN** the admin clicks Begin intel, no default exists and no active AI system is assigned the Company Intel prompt
- **THEN** no Role intel is created and the dialog explains that an AI system using the Company Intel prompt is needed, linking to AI Systems

#### Scenario: Start from the Discussion page

- **WHEN** the admin uses the Intel header item on an application without Role intel and a system is available
- **THEN** the intel page opens and the first turn streams there without further action

#### Scenario: Chosen system is not eligible

- **WHEN** a start request names an AI system that is inactive or not assigned the Company Intel prompt, and is not the default
- **THEN** the request fails validation and no Role intel is created

### Requirement: Company Intel list page

The system SHALL provide a Company Intel page in the Employment hub listing every Role intel whose application is not deleted, most recently active first, showing the company (or "Unidentified"), the position linked to its application's Discussion page, the state, the AI system and the last activity. Each row SHALL link to its intel page. The page SHALL NOT offer a way to start intel.

#### Scenario: Listing

- **WHEN** the admin opens the Company Intel page
- **THEN** each Role intel of a non-deleted application is listed with its company, position, state, AI system and last activity

#### Scenario: Nothing to start here

- **WHEN** the admin opens the Company Intel page
- **THEN** no control on the page starts a new intel session

#### Scenario: Empty list

- **WHEN** no Role intel exists
- **THEN** the page explains that intel is started from an application, and links to Applications

#### Scenario: Deleted application

- **WHEN** an application with Role intel is deleted
- **THEN** its Role intel no longer appears on the list, and its Company remains

### Requirement: Intel page

The system SHALL provide an intel page per Role intel showing the session's chat, the Company with its metadata and insights, the Role intel findings, the state, and a link to the application's Discussion page. While the Role intel is `queued` or `running`, the page SHALL show that it is in progress and update when the run finishes. When `failed`, it SHALL show the reason and a Retry action that runs the pending turn again.

#### Scenario: Ready intel

- **WHEN** the admin opens the intel page of a `ready` Role intel
- **THEN** the chat, the Company panel with metadata and insights, and the Role panel are shown

#### Scenario: In progress

- **WHEN** the admin opens the intel page while the background turn is running
- **THEN** the page shows that research is in progress and shows the results once the run finishes, without a manual reload

#### Scenario: Retry

- **WHEN** the admin clicks Retry on a `failed` Role intel
- **THEN** the first turn runs again in the same session and the state returns to `running`

### Requirement: Correcting a wrong company

The intel page SHALL offer a Wrong company action whenever a Company is attached. It SHALL detach the Company from the Role intel and remember it as rejected for that Role intel. The admin SHALL then either choose an existing Company, which is attached directly, or give a hint (the correct name and/or website), which is sent to the agent in the same session so it researches and attaches the correct company. A rejected Company that no other Role intel refers to SHALL be deleted with its insights. The agent SHALL NOT be able to attach a Company rejected for that Role intel.

#### Scenario: Re-research with a hint

- **WHEN** the admin marks the company wrong and gives the hint "Acme Robotics, acmerobotics.com"
- **THEN** the Company is detached, a message carrying the hint is added to the session, and the agent's turn attaches a Company for acmerobotics.com

#### Scenario: Choose an existing company

- **WHEN** the admin marks the company wrong and chooses an existing Company instead of giving a hint
- **THEN** that Company is attached and no agent turn runs

#### Scenario: Rejected company is unreferenced

- **WHEN** the rejected Company is referred to by no other Role intel
- **THEN** it and its insights are deleted

#### Scenario: Rejected company is shared

- **WHEN** the rejected Company is still referred to by another application's Role intel
- **THEN** it is kept, unchanged, for that other application

#### Scenario: Agent repeats the mistake

- **WHEN** the agent tries to attach a Company with the rejected name or website domain to the same Role intel
- **THEN** the tool refuses with a message telling the agent that company was rejected, and nothing is attached

### Requirement: Company Intel requires resume-editing permission

Every Company Intel page and endpoint SHALL require an authenticated user holding the `edit-resume` permission.

#### Scenario: Unauthorized user

- **WHEN** a signed-in user without `edit-resume` requests the Company Intel page, an intel page or any intel endpoint
- **THEN** the request is refused with 403

#### Scenario: Anonymous visitor

- **WHEN** an unauthenticated visitor requests the Company Intel page
- **THEN** they are redirected to login
