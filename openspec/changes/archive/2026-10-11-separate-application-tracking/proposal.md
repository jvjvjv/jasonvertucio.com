## Why

Job tracking lives inside the Targeted Resume Builder, so a job can only be tracked by pretending it has a targeted resume. Applying with the main resume alone works today only through a workaround (`base_resume = true` on a `TargetedResume` with no tailored content), jobs that were analyzed but never finalized exist only as `AiConversation.context` JSON (48 of 101 sessions locally), and "passed" lives on the conversation while every other status lives on the resume. Tracking an application and building a targeted resume are two different jobs and need two clear workflows.

## What Changes

- **New `Application` model** — the tracked job. Owns the job details (`job_url_id`, `company_name`, `position`, `job_description`, new nullable `location`), the fit assessment (`fit_score`, `fit_summary`), the `status`, a nullable `ai_conversation_id`, the `resume_version_id` used, and a nullable `targeted_resume_id`.
- **BREAKING (data model)**: `TargetedResume` is stripped to the document itself — `resume_version_id`, `title`, `tailored_data`, `docx_path`, `pdf_path`. `title` stays because it is the headline printed in the document's letterhead, not a job or chat title. The moved columns and the `base_resume` flag are dropped.
- **BREAKING (data model)**: `TargetedResumeStatusUpdate` becomes `ApplicationStatusUpdate`, keyed on `application_id`.
- **Every job session is an Application**, created the moment the user clicks "Begin Analysis" or "I Applied". "Draft" and "Passed" become Application statuses; "Finalized" stops being a status (it is simply "has a targeted resume"). Job details stop living in `conversation.context`.
- **Existing data is migrated**: every `TargetedResume` and every targeted-resume conversation without one becomes an Application; status history, cover letters and applied dates carry over; the `base_resume` placeholder rows become main-resume applications.
- **Applications list page** replaces the Targeted Resume list as the tracking surface: filters move behind a Filter button (dialog), a "Uses targeted resume" filter is added, and a Resume column shows an em dash or an EditNote button linking to the edit page.
- **Targeted Resumes list page** — a separate page listing only the documents (base version, title, last edited, downloads, edit), each linking back to its application.
- **New Session flow** — both lists get `+ New Session`, opening the existing job form (URL parse → autofill title, location, company, description) with two actions: **Begin Analysis** (starts the AI session, as today) and **I Applied** (confirms which resume was used, records it, marks the application applied — no AI session).
- **Application Discussion page** replaces the builder Show page, keyed on the Application. An application without an AI session can start one later. The manual-edit tab is removed from the tab header.
- **Edit Targeted Resume page** — a standalone page (like Edit Cover Letter), reached from the Edit button inside the Resume card and from both lists.
- **A targeted resume can be discarded.** If one was built but will not be used, it is deleted and the application falls back to the main resume — an application never holds a targeted resume it did not use.
- **Cover letters attach to the Application** (`application_id`, replacing `targeted_resume_id`), so a main-resume application can have a cover letter and a cover letter no longer requires a finalized targeted resume.
- **Application Metrics** gains date filtering (30d / 90d / This year / All time presets plus a custom range, by date applied); the Timeline is collapsed under an accordion.
- New application-keyed admin API endpoints replace the conversation-keyed `targeted-builder` endpoints; old page URLs redirect.

## Capabilities

### New Capabilities

- `application-tracking`: The Application record and its lifecycle — creation via Begin Analysis or I Applied, statuses and status history, the Applications list and its filters, the Application Discussion page, cover letter attachment, deletion, and migration of existing data.
- `targeted-resume-list`: The Targeted Resumes list page — which documents it shows, its columns and actions.
- `application-metrics`: The Application Metrics dashboard — which applications it counts, date filtering, and the collapsed timeline.

### Modified Capabilities

- `targeted-resume-manual-editing`: The manual markdown editor moves from a tab on the builder Show page to a standalone Edit Targeted Resume page, reached from the Resume card and the lists.

## Impact

- **Database**: new `applications` table; `targeted_resume_status_updates` → `application_status_updates`; `cover_letters.targeted_resume_id` → `application_id`; eight columns plus `base_resume` dropped from `targeted_resumes`. Data migration must run against both `jasonvertucio` and the `wink` test database.
- **Backend**: `TargetedResumeController` (split into application and targeted-resume controllers), `TargetedResumeService`, `TargetedResumeMetricsService`, `TargetedResumeStatusResolver`, both status enums, `CoverLetter`, `AiConversation`, `JobUrl`, `ResumeVersion`, `CoverLetterController`, and the eight tools under `app/Services/Mcp/Tools/TargetedResume/`.
- **Routes**: `routes/admin-resume.php`, `routes/api-web.php`; `/admin/resume/targeted-builder*` pages redirect to their application equivalents.
- **Frontend**: `resources/js/admin/pages/resume/targeted/*` reorganized into `applications/` and `targeted/`; `resume/metrics/Index.tsx`; `ai/conversations/Show.tsx` link; `resources/js/types/index.ts`; `utils/targetedResumeStatus.ts`.
- **Tests**: the nine targeted-resume feature/unit test files are adapted to the new model (none removed).
- **Docs**: `CLAUDE.md` gains an Application Tracking section and updated model table.
- **Not affected**: document rendering (DOCX/PDF pipeline), the public MCP server, the `jvjvjv/code-talker` package.
