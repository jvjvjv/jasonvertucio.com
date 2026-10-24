## Context

See proposal.md — Why. The current shape, as observed in the code:

- The list page (`TargetedResumeController::index`) queries **`AiConversation`** where `feature = 'targeted-resume'` and left-joins `targetedResume`. A `TargetedResume` row exists only after the first finalize (or after Mark Applied, see below). Before that, company, job title, description, job URL, fit score and fit summary live in `conversation.context` JSON, and `TargetedResumeService` copies them onto the resume on finalize and again in `updateConversationMetadata`.
- `addStatusUpdate` on a session with no resume creates a `TargetedResume` with `tailored_data = null` and `base_resume = true`. That is today's "applied with the main resume".
- Two enums overlap: `TargetedResumeStatus` (draft, finalized, + pipeline) and `TargetedResumeApplicationStatus` (pipeline only, with `allowedNext()`/`isTerminal()`). "Pass" is a third status, on `AiConversation` (package enum `AiConversationStatus`: active, completed, pass). The list filter merges both.
- `CoverLetter` belongs to `targeted_resume_id`; `saveCoverLetter` throws unless a targeted resume exists. Both `generateFilename()` methods reach the conversation UUID through the targeted resume.
- Eight MCP tools under `app/Services/Mcp/Tools/TargetedResume/` read and write job/fit/status through `conversation->targetedResume` or `conversation.context`.
- Local data: 101 sessions (44 pass, 46 completed, 11 active), 53 targeted resumes (17 finalized, 21 applied, 14 rejected, 1 interviewed; 5 `base_resume`, 4 with null `tailored_data`), 61 status updates, 9 cover letters (2 standalone).

Constraints: tests run against the real `wink` database with `DatabaseTransactions` (never `RefreshDatabase`); migrations must be run against both `jasonvertucio` and `wink`; `vendor/jvjvjv/code-talker` must not be edited; production deploys are the developer's.

## Goals / Non-Goals

**Goals:**

- One row per tracked job, with one authoritative status column.
- `TargetedResume` is only a document; nothing about a job is stored on it.
- No job data in `conversation.context` after migration — one source of truth.
- A lossless, reversible data migration.

**Non-Goals:**

- Changing the analysis/tailoring prompts or the chat stream contract.
- Changing document rendering, or on-demand generation semantics.
- Multiple targeted resumes or multiple AI sessions per application.
- Changing the code-talker package or its `AiConversationStatus` enum.
- Restyling the metrics charts beyond the filter bar and the timeline accordion.

## Decisions

### 1. `applications` schema and foreign-key direction

`applications`: `id`, `resume_version_id` (FK, required), `targeted_resume_id` (nullable, unique), `ai_conversation_id` (nullable, unique), `job_url_id` (nullable), `company_name`, `position`, `location` (nullable, new), `job_description` (longtext), `fit_score`, `fit_summary`, `status`, timestamps, `deleted_at`.

`targeted_resumes` keeps `id`, `resume_version_id`, `title`, `tailored_data`, `docx_path`, `pdf_path`, timestamps.

- **The FK lives on `applications`** (`targeted_resume_id`), as requested; `TargetedResume::application()` is a `hasOne`. *Alternative:* `targeted_resumes.application_id`. Rejected only because the request specifies the direction; the unique index gives the same 1:1 guarantee.
- **The column is `resume_version_id`, not `resume_id`.** The request says `resume_id`, but the model is `ResumeVersion` and every sibling table (`targeted_resumes`, `cover_letters`, `resume_edit_candidates`) uses `resume_version_id`; a lone `resume_id` would break Eloquent's default relation key. `TargetedResume.resume_version_id` stays too: it is the base the document was tailored from, which is a fact about the document.
- **`location` is added.** The form already collects it but it is only ever written into the first chat message; an "I Applied" application has no chat message to hold it.
- **`title` stays on `TargetedResume`**, departing from the original column list. It is neither a job title (`position` is) nor a chat title (`AiConversation.title` is, and stays editable in the details form): it is the professional headline parsed from the tailored summary by `extractTitleFromSummary`, and `TargetedResumeDocumentService` substitutes it into the letterhead's `{title}` placeholder. It is a rendering input of the document. The Application needs no title of its own — lists show company and position; the Discussion page header uses the conversation title when there is a session and "Company — Position" otherwise.
- **Soft deletes on `applications`.** Today delete soft-deletes the conversation, which is what hides the row; with the list keyed on applications the application needs its own `deleted_at`. Deleting an application also soft-deletes its conversation so the AI Conversations admin stays consistent.

### 2. One status enum, on the Application

`App\Enums\ApplicationStatus`: `Draft`, `Passed`, `Applied`, `Interviewing`, `Interviewed`, `Offered`, `Accepted`, `Hired`, `Rejected`, carrying `isPipeline()`, `isTerminal()` and `allowedNext()` (moved from `TargetedResumeApplicationStatus`). Both old enums are deleted. `ApplicationStatusUpdate.status` is cast to the same enum and validated to pipeline cases.

- **`finalized` is not a status.** It only ever meant "a targeted resume exists", which `targeted_resume_id IS NOT NULL` now says directly and the new "Uses targeted resume" filter exposes. Migrated `finalized` rows become `draft`.
- **`passed` moves to the Application.** `pass` also still sets `AiConversation.status = Pass` when a conversation exists, purely so the generic AI Conversations admin keeps showing it; nothing in application code reads it back. Likewise finalize keeps setting `Completed`.
- `passed → applied` is allowed (mirrors today, where Mark Applied stays enabled on a passed session). `draft`/`passed` allow `Applied` as the only next pipeline status.
- `TargetedResumeStatusResolver` becomes `ApplicationStatusResolver` and loses its `conversationStatus` fallback argument — there is no second status source any more. `resources/js/admin/utils/targetedResumeStatus.ts` mirrors it and is renamed in step.

*Alternative considered:* keep two enums (lifecycle vs pipeline). Rejected — the controller already converts between them with `::from($other->value)` at five call sites; that conversion is the smell.

### 3. The Application is created up front; `conversation.context` stops holding job data

`ApplicationService::createForAnalysis()` creates the Application and the conversation in one DB transaction; `createApplied()` creates the Application plus its first `applied` status update. `TargetedResumeService::startConversation()` takes the Application and no longer writes `job_title`, `job_description`, `job_url_id`, `resume_version_id`, `company_name`, `fit_score`, `fit_summary` into `context` — it keeps only `step` and `auto_start_pending`.

Every reader moves to the Application: `saveTailoredResume`, `saveCoverLetter`, `updateConversationMetadata` (now updates the Application directly; the `*_manual` flags that stop the assistant overwriting hand-edited fields stay in `context` — they are session behaviour, not job data), `syncConversationMetadataFromAssistantResponse`, and the tools `GetJobDescriptionTool`, `GetTargetedResumeContextTool`, `UpdateFitAssessmentTool`, `UpdateStatusTool`, `SaveTailoredResumeTool`, `SaveCoverLetterTool`. `AiConversation` gains `application(): HasOne`; `targetedResume()` becomes a `hasOneThrough` the application so existing call sites that only need the document keep working.

*Alternative considered:* keep mirroring into `context`. Rejected — the mirror is exactly what produced the three-way sync in `updateConversationMetadata`.

### 4. Which resume an application "used"

- Application has a targeted resume → it used the targeted resume. The Mark Applied confirmation says so and offers no choice.
- Otherwise → the admin picks a `ResumeVersion` (default: current), stored in `resume_version_id`.

"A targeted resume exists but I sent the main one" is not a state the model can hold, by intent: a targeted resume that will not be used is **discarded**. `ApplicationService::discardTargetedResume()` runs in one transaction — null `applications.targeted_resume_id`, `invalidateDocuments()`, delete the row (`document_downloads.targeted_resume_id` is already `nullOnDelete`), append a `resume_discarded` assistant-visible message to the conversation (same mechanism as `recordManualEditMessage`). It is a hard delete: the tailored markdown is still in the chat transcript and a re-finalize rebuilds it. It is refused (409) once the application has an `applied` status update — from then on the document is the record of what was sent. Route: `DELETE /admin/resume/targeted-resumes/{targetedResume}` on `Admin\TargetedResumeController`.

*Alternative considered:* a `used_targeted_resume` boolean. Rejected — it lets an unused document linger and makes "Uses targeted resume" ambiguous.

### 5. Routes and controllers

`TargetedResumeController` (670 lines, three responsibilities) is split:

| Controller | Owns |
| --- | --- |
| `Admin\ApplicationController` | index, create, store, show, update (job details), apply, pass, destroy |
| `Admin\ApplicationSessionController` | begin analysis, chat (SSE), finalize, finalize-cover-letter |
| `Admin\ApplicationStatusUpdateController` | add / update / delete history entries |
| `Admin\TargetedResumeController` | index (list), edit, updateMarkdown, download, regenerate, destroy (discard) |

Pages (`routes/admin-resume.php`, `can:edit-resume`):

```
GET    /admin/resume/applications                       applications.index
GET    /admin/resume/applications/new                   applications.create
GET    /admin/resume/applications/{application}         applications.show
DELETE /admin/resume/applications/{application}         applications.destroy
GET    /admin/resume/targeted-resumes                   targeted.index
GET    /admin/resume/targeted-resumes/{targetedResume}/edit   targeted.edit
DELETE /admin/resume/targeted-resumes/{targetedResume}        targeted.destroy
GET    /admin/resume/targeted-resume/{targetedResume}/download/{format}   (unchanged)
POST   /admin/resume/targeted-resume/{targetedResume}/regenerate          (unchanged)
GET    /admin/resume/targeted-builder[/new|/{conversation}]  → 301 to the above
```

JSON endpoints (`routes/api-web.php`, same group and prefix as today's `targeted-builder` block):

```
POST   applications                                {job fields, intent: analyze|applied, ai_system_id?, resume_version_id?}
PUT    applications/{application}                  job details
POST   applications/{application}/analysis         begin analysis on an existing application
POST   applications/{application}/apply            {resume_version_id?, occurred_at?}
POST   applications/{application}/pass
POST   applications/{application}/chat             SSE, same frames as today
POST   applications/{application}/finalize
POST   applications/{application}/finalize-cover-letter
POST|PUT|DELETE applications/{application}/status-updates[/{statusUpdate}]
PUT    targeted-resume/{targetedResume}            (unchanged — updateMarkdown)
```

One `POST applications` with an `intent` rather than two endpoints, so "I Applied" is a single transaction: a half-created application with no status entry cannot be left behind by a failed second request. Validation lives in Form Requests (`StoreApplicationRequest`, `UpdateApplicationRequest`, `ApplyApplicationRequest`, `StoreApplicationStatusUpdateRequest`, `UpdateApplicationStatusUpdateRequest`), replacing the inline `$request->validate()` calls. Parser confirm/reject routes move under `applications/parser/...` with the old names redirected.

The chat endpoint keeps the SSE frame contract (`chat-stream-contract`) untouched; only its URL and route binding change. `chat`, `finalize*` return 409 when the application has no conversation.

### 6. Frontend layout

```
resources/js/admin/pages/resume/applications/
  Index.tsx  FiltersDialog.tsx  Create.tsx  AppliedConfirmDialog.tsx
  Show.tsx   ChatPanel.tsx  DetailsForm.tsx  ResumeCard.tsx  StatusBar.tsx
  StatusHistoryList.tsx  StatusUpdateForm.tsx  useStatusUpdates.ts  useFinalizeArtifacts.ts …
resources/js/admin/pages/resume/targeted/
  Index.tsx  Edit.tsx  TailoredResumeEditor.tsx  tailoredResumeParser.ts  useUpdateTailoredMarkdown.ts
```

Existing components are moved and renamed (`git mv`), not rewritten: `Builder*` → the names above, `JobDetailsForm`/`JobURLInputSection`/`ParseResultsDisplay` move with `Create`. `Show.tsx` drops its third tab; `ResumeCard` is extracted from `BuilderMetadataForm` and gets the Edit button. `AppliedConfirmDialog` is shared by Create ("I Applied") and Show (Mark Applied). The filter dialog is an MUI `Dialog` (full-screen below `sm`, which is the action-sheet behaviour on a phone) holding the existing status multi-select plus a three-way Any/Yes/No toggle; search stays inline. No gradients or box shadows, per CLAUDE.md.

### 7. Cover letters

`cover_letters.application_id` (nullable FK) replaces `targeted_resume_id`. `saveCoverLetter` keys `updateOrCreate` on `application_id` and drops the "finalize the resume first" guard; `resume_version_id` comes from the application. `CoverLetterController::create` accepts `?application={id}` to prefill company/position and link the letter, giving a main-resume application a non-AI route to a cover letter. Both `generateFilename()` methods read the conversation UUID via the application and fall back to `app-{id}` when there is no conversation (today's fallback is the literal `unknown`, which would collide across main-resume applications).

### 8. Metrics

`TargetedResumeMetricsService` → `ApplicationMetricsService`, querying `Application` with `statusUpdates`. `build(?CarbonInterface $from, ?CarbonInterface $to)` filters the collection by `appliedAt()` (earliest `applied` entry — unchanged definition) before any section is computed, so every section is consistent by construction. Presets are resolved **server-side** from a `range` query parameter (`30d`, `90d`, `ytd`, `all`), with `from`/`to` overriding it for a custom range, validated in `ApplicationMetricsRequest`. The page drives it with Inertia partial reloads (`router.get` with `preserveState`). The ghosted calculation still uses real "now", not the range end — an application is ghosted today or it is not.

*Alternative:* filter client-side over the full payload. Rejected: cycle times and KPIs are computed in PHP; duplicating them in TypeScript recreates the two-implementations problem the status resolver already has.

### 9. Migration

Three migrations, so the destructive step is separable:

1. **`create_applications_table`** — schema only, plus `application_status_updates` and `cover_letters.application_id` (nullable, added alongside the old columns).
2. **`backfill_applications`** — data only, chunked, idempotent (skips a targeted resume or conversation that already has an application):
   - Each `targeted_resumes` row → an application copying the moved columns. Status: `finalized`/`draft` → `draft`, unless its conversation's status is `pass`, then `passed`; pipeline statuses copy as-is. `targeted_resume_id` is set **only if `tailored_data` is not null**. (The rule is "has content", not the `base_resume` flag: one of the five flagged rows locally has since been finalized and is a real document.)
   - Each targeted-resume conversation with no targeted resume → an application from `context` (`company_name` ?? 'Unknown Company', `job_title` ?? 'Unknown Position', `job_description` ?? '', `job_url_id`, `fit_score`, `fit_summary`, `resume_version_id` ?? current version), `passed` if the conversation is `pass`, else `draft`. `created_at`/`updated_at` copy from the conversation.
   - `deleted_at` copies from the conversation (`withTrashed`).
   - Status updates copy to `application_status_updates` via the resume → application map; `cover_letters.application_id` likewise.
   - Uses the query builder, not models, so it stays valid after the models change.
3. **`strip_targeted_resumes`** — deletes content-less targeted resumes (after asserting none is referenced by `document_downloads`), drops `cover_letters.targeted_resume_id`, the `targeted_resume_status_updates` table, and the eight moved columns plus `base_resume`. Its `down()` re-adds the columns and copies values back from `applications`; main-resume applications are re-materialized as `base_resume` placeholders.

Migration 2 ends by asserting counts (applications = targeted resumes + resume-less conversations; status updates and linked cover letters equal before and after) and throws on mismatch, so a bad backfill fails before migration 3 can drop anything.

## Risks / Trade-offs

- **A wrong backfill is destructive once step 3 runs** → count assertions gate step 3; step 3 has a real `down()`; the deploy plan takes a `mysqldump` of the four tables first.
- **MCP tools change shape mid-conversation** → an in-flight session started before deploy has job data only in `context`. The backfill covers every existing conversation, so after migration all of them have an application; tools read only the application.
- **`hasOneThrough` for `AiConversation::targetedResume()`** hides the extra hop → acceptable for read paths; write paths go through the application explicitly.
- **Old route names disappear** (`admin.resume.targeted.show` etc.) → grep-and-replace in PHP and TSX is part of the tasks; redirects cover bookmarks, not `route()` calls.
- **Large rename touches ~45 files** → done as `git mv` + edits in dependency order (schema → models → services → tools → controllers → pages), with the existing test files adapted alongside each layer rather than at the end.
- **Discard is a hard delete** → confirmed in the UI, blocked after applying, and the content remains in the chat transcript.

## Migration Plan

Developer-owned (production is not touched by the implementer):

1. Back up: `mysqldump <db> targeted_resumes targeted_resume_status_updates cover_letters ai_conversations > pre-applications.sql`.
2. Deploy code; `php artisan migrate` (all three migrations). A count mismatch aborts in migration 2 with nothing dropped.
3. `sudo supervisorctl restart <program-name>` — the `queue:work` worker holds old model definitions.
4. Smoke check: Applications list count equals the old list count; open one applied, one passed, one main-resume application; open Metrics on All time and compare totals with the pre-deploy figures.

Rollback: `php artisan migrate:rollback --step=3` restores the old columns and data from `applications`; redeploy the previous release. If the rollback itself fails, restore the four tables from the dump.

Locally: run the migrations against both databases (`php artisan migrate` and `DB_DATABASE=wink php artisan migrate`).

## Open Questions

- Should the Resume hub page get cards for Applications and Targeted Resumes, and in what order relative to Metrics? Layout only; does not affect routes or behaviour.
