# AI Tools, Targeted Resume Builder & AI Cover Letter Builder

## Overview

This document tracks the remaining implementation phases for the AI-powered features on the admin dashboard. Phase 1 (Foundation) is complete.

### Phase Summary

| Phase | Description | Status |
|-------|-------------|--------|
| 1 | AI Systems Management (CRUD, permissions, service layer) | **Complete** |
| 2 | Chat Infrastructure (conversations, messages, Alpine.js chat UI) | Pending |
| 3 | Targeted Resume Builder (AI-tailored resumes via chat) | Pending |
| 4 | AI Cover Letter Builder (AI-generated cover letters via chat) | Pending |
| 5 | Polish & Cross-linking | Pending |

### What Phase 1 Delivered

- `ai_systems`, `ai_system_feature_defaults`, `ai_interaction_logs` tables
- `AiSystem`, `AiSystemFeatureDefault`, `AiInteractionLog` models
- `AiClientFactory` service — creates `ClaudeService` instances from database-stored AI system configs
- `AiSystemController` with full CRUD + interaction logs viewer
- AI Tools hub at `/admin/ai` with nav block on admin dashboard
- `manage-ai-tools` permission (migration + seeder)
- "Resume Editor" renamed to "Resume Builder" in UI

### Important: UUID User IDs

The `users.id` column uses `char` (UUID), not `bigint`. All migrations referencing `user_id` must use `foreignUuid('user_id')` instead of `foreignId('user_id')`.

---

## Phase 2: Chat Infrastructure

### 2.1 Database Migrations

**`ai_conversations` table:**

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| user_id | uuid FK | References `users.id` — use `foreignUuid` |
| ai_system_id | bigint FK | |
| feature | varchar(100) | 'targeted-resume', 'cover-letter' |
| title | varchar(255) nullable | Auto-generated from job title/company |
| status | varchar(50) default 'active' | 'active', 'completed', 'cancelled' |
| context | json nullable | Job title, description, company, current step |
| timestamps | | |

**`ai_conversation_messages` table:**

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| ai_conversation_id | bigint FK (cascade) | |
| role | varchar(20) | 'user', 'assistant', 'system' |
| content | longtext | |
| metadata | json nullable | Token usage, model, tool calls |
| created_at | timestamp | |

**`add_ai_conversation_id_to_ai_interaction_logs` migration:**

Add nullable `ai_conversation_id` FK to `ai_interaction_logs`.

### 2.2 Models

**`AiConversation`** (`app/Models/AiConversation.php`):
- `$casts`: `context` as `array`
- Relations: `user()`, `aiSystem()`, `messages()` (ordered by `created_at`), `interactionLogs()`, `targetedResume()`

**`AiConversationMessage`** (`app/Models/AiConversationMessage.php`):
- `$casts`: `metadata` as `array`
- Relations: `conversation()`

Update `AiInteractionLog` — add `conversation(): BelongsTo` relationship.
Update `AiSystem` — add `conversations(): HasMany` relationship.

### 2.3 Chat Alpine.js Component

Create `resources/js/ai-chat.js`:
- Uses `fetch()` with `ReadableStream` for SSE streaming (POST endpoints, not EventSource)
- Displays message history with role-based styling (user messages right-aligned, assistant left-aligned)
- Real-time streaming: content deltas appended live as they arrive
- Input field with send button, disabled during streaming
- Status indicators: thinking spinner, streaming indicator, error display
- Contextual action buttons (e.g., "Proceed", "Finalize Resume")
- Auto-scroll to bottom on new messages
- Markdown rendering for assistant messages

Create shared partial `resources/views/admin/ai/_chat.blade.php` that includes the Alpine component.

Add `ai-chat.js` to `vite.config.js` inputs.

### 2.4 Key Files

| File | Purpose |
|------|---------|
| `database/migrations/*_create_ai_conversations_table.php` | Conversations table |
| `database/migrations/*_create_ai_conversation_messages_table.php` | Messages table |
| `database/migrations/*_add_ai_conversation_id_to_ai_interaction_logs.php` | Link logs to conversations |
| `app/Models/AiConversation.php` | Conversation model |
| `app/Models/AiConversationMessage.php` | Message model |
| `resources/js/ai-chat.js` | Alpine.js chat component |
| `resources/views/admin/ai/_chat.blade.php` | Shared chat partial |
| `vite.config.js` | Add ai-chat.js entry point |

---

## Phase 3: Targeted Resume Builder

### 3.1 Database Migration

**`targeted_resumes` table:**

| Column | Type | Notes |
|--------|------|-------|
| id | bigint PK | |
| resume_version_id | bigint FK | Source resume version |
| ai_conversation_id | bigint FK nullable | Conversation that generated it |
| company_name | varchar(255) | |
| position | varchar(255) | |
| job_description | longtext | Original job description input |
| tailored_data | json | Full resume data (same shape as `DatabaseResumeDataService::getAllEditableData()`) |
| fit_score | tinyint unsigned nullable | 1-100 |
| fit_summary | text nullable | AI explanation of fit |
| docx_path | varchar(255) nullable | |
| pdf_path | varchar(255) nullable | |
| status | varchar(50) default 'draft' | 'draft', 'finalized', 'archived' |
| timestamps | | |

### 3.2 Model

**`TargetedResume`** (`app/Models/TargetedResume.php`):
- `$casts`: `tailored_data` as `array`
- Relations: `resumeVersion()`, `conversation()`, `coverLetters()`
- Methods: `docxExists()`, `pdfExists()`, `generateFilename()`
- DOCX template: `resources/resume/2026 targeted resume template.docx`

Update `ResumeVersion` — add `targetedResumes(): HasMany`.

### 3.3 Service

**`TargetedResumeService`** (`app/Services/TargetedResumeService.php`):

- `startConversation(AiSystem, string $jobTitle, string $jobDescription, ResumeVersion): AiConversation`
  - Creates conversation with context (job info, step tracking)
  - Builds system prompt with full resume data from `DatabaseResumeDataService::getDocxData()`
  - Sends initial message asking AI to analyze the job description
- `continueConversation(AiConversation, string $userMessage): Generator`
  - Loads full message history, appends user message, streams response
  - Logs interaction to `ai_interaction_logs`
- `buildSystemPrompt(ResumeVersion): string`
  - Includes structured resume data as context
  - Defines the multi-step flow:
    1. Describe the company (high-level/important notes only)
    2. Determine eligibility using the resume
    3. Ask for additional info not in the resume
    4. Provide fit assessment, ask if user wants to proceed
    5. If yes, tailor the resume to the job posting
    6. Assist with application questions
- `extractTailoredResume(AiConversation): array` — parses AI output into resume data structure
- `saveTailoredResume(AiConversation, array $data): TargetedResume`

### 3.4 Controller

**`TargetedResumeController`** (`app/Http/Controllers/Admin/TargetedResumeController.php`):

| Method | Route | Description |
|--------|-------|-------------|
| `index()` | `GET /admin/resume/targeted-builder` | List past targeted resumes |
| `create()` | `GET /admin/resume/targeted-builder/new` | Form: AI system dropdown, job title, job description |
| `start()` | `POST /admin/resume/targeted-builder/start` | Create conversation, return ID as JSON |
| `show()` | `GET /admin/resume/targeted-builder/{conversation}` | View conversation with chat interface |
| `chat()` | `POST /admin/resume/targeted-builder/{conversation}/chat` | SSE streaming endpoint |
| `finalize()` | `POST /admin/resume/targeted-builder/{conversation}/finalize` | Save tailored resume to DB |
| `download()` | `GET /admin/resume/targeted-resume/{targetedResume}/download/{format}` | Download DOCX/PDF |

Routes go in the `edit-resume` middleware group.

### 3.5 Views

- `resources/views/admin/resume/targeted/index.blade.php` — list with status, company, position
- `resources/views/admin/resume/targeted/create.blade.php` — form + chat (handles LinkedIn paste in job description textarea)
- `resources/views/admin/resume/targeted/show.blade.php` — chat interface + finalized resume display

### 3.6 UI Updates

- Add "Targeted Resume Builder" nav block to `resources/views/admin/resume/hub.blade.php`
- Add "Targeted Resume Builder" link to AI Tools hub

### 3.7 Form Request

**`StartTargetedResumeRequest`** (`app/Http/Requests/StartTargetedResumeRequest.php`):
- `ai_system_id` — required, exists in `ai_systems`
- `job_title` — nullable, string, max:255
- `job_description` — required, string

### 3.8 Key Files

| File | Purpose |
|------|---------|
| `database/migrations/*_create_targeted_resumes_table.php` | Targeted resumes table |
| `app/Models/TargetedResume.php` | Targeted resume model |
| `app/Services/TargetedResumeService.php` | AI orchestration |
| `app/Http/Controllers/Admin/TargetedResumeController.php` | Controller |
| `app/Http/Requests/StartTargetedResumeRequest.php` | Validation |
| `resources/views/admin/resume/targeted/*.blade.php` | Views |
| `resources/views/admin/resume/hub.blade.php` | Add nav block |
| `routes/web.php` | Add routes |

---

## Phase 4: AI Cover Letter Builder

### 4.1 Database Migration

Add `targeted_resume_id` to `cover_letters` table:

```php
$table->foreignId('targeted_resume_id')->nullable()->constrained('targeted_resumes')->nullOnDelete();
```

A cover letter can link to either `resume_version_id` (standard) or `targeted_resume_id` (targeted), or both.

### 4.2 Model Updates

Update **`CoverLetter`** (`app/Models/CoverLetter.php`):
- Add `targeted_resume_id` to `$fillable`
- Add `targetedResume(): BelongsTo`
- Add `sourceResume()` accessor — returns associated resume data from whichever FK is set

### 4.3 Service

**`AiCoverLetterService`** (`app/Services/AiCoverLetterService.php`):

- `startConversation(AiSystem, string $jobDescription, ResumeVersion|TargetedResume, ?string $jobTitle): AiConversation`
- `continueConversation(AiConversation, string $userMessage): Generator`
- `extractCoverLetter(AiConversation): array` — parses AI output into cover letter fields (greeting, message_body, closing, etc.)
- `saveCoverLetter(AiConversation, array $data): CoverLetter`

### 4.4 Controller

**`AiCoverLetterController`** (`app/Http/Controllers/Admin/AiCoverLetterController.php`):

| Method | Route | Description |
|--------|-------|-------------|
| `create()` | `GET /admin/ai-cover-letter/new` | Form: AI system, job description, resume selection |
| `start()` | `POST /admin/ai-cover-letter/start` | Create conversation |
| `show()` | `GET /admin/ai-cover-letter/{conversation}` | Chat + result |
| `chat()` | `POST /admin/ai-cover-letter/{conversation}/chat` | SSE streaming |
| `finalize()` | `POST /admin/ai-cover-letter/{conversation}/finalize` | Create CoverLetter, generate DOCX/PDF |

Routes go in the `manage-unauthenticated-viewers` admin group.

### 4.5 Views

- `resources/views/admin/cover-letters/ai-create.blade.php` — AI cover letter builder with form + chat

### 4.6 UI Updates

- Add "AI Cover Letter" button to cover letter management index
- Update cover letter index to show targeted resume link when present
- Cross-link: from targeted resume show page, offer "Generate Cover Letter" button

### 4.7 Form Request

**`StartAiCoverLetterRequest`** (`app/Http/Requests/StartAiCoverLetterRequest.php`):
- `ai_system_id` — required, exists in `ai_systems`
- `job_description` — required, string
- `job_title` — nullable, string, max:255
- `resume_version_id` — nullable, exists in `resume_versions` (use standard resume)
- `targeted_resume_id` — nullable, exists in `targeted_resumes` (use targeted resume)
- At least one of `resume_version_id` or `targeted_resume_id` must be provided

### 4.8 Key Files

| File | Purpose |
|------|---------|
| `database/migrations/*_add_targeted_resume_id_to_cover_letters.php` | Link cover letters to targeted resumes |
| `app/Models/CoverLetter.php` | Update with new relationship |
| `app/Services/AiCoverLetterService.php` | AI orchestration |
| `app/Http/Controllers/Admin/AiCoverLetterController.php` | Controller |
| `app/Http/Requests/StartAiCoverLetterRequest.php` | Validation |
| `resources/views/admin/cover-letters/ai-create.blade.php` | View |
| `routes/web.php` | Add routes |

---

## Phase 5: Polish & Cross-linking

- AI Tools hub (`resources/views/admin/ai/index.blade.php`): Add nav blocks for Targeted Resume Builder and AI Cover Letter Builder
- Resume hub: Already has Targeted Resume Builder link from Phase 3
- Targeted resume show page: Add "Generate Cover Letter" button linking to AI Cover Letter Builder
- Cover letter create/edit forms: Add optional targeted resume dropdown
- Ensure nav block ordering is sensible on admin dashboard

---

## Future Considerations

- **OpenAI provider**: Only Anthropic is implemented. `AiClientFactory` is designed for multiple providers but only `ClaudeService` is wired up.
- **LinkedIn paste parsing**: Job description textarea accepts raw paste. Structured parsing could be added later.
- **Application question assistance**: Handled conversationally in the chat — no separate UI needed.
- **Rate limiting**: Not implemented initially. Could be added to `AiClientFactory`.
- **DOCX generation for targeted resumes**: Uses `resources/resume/2026 targeted resume template.docx`. Reuses the `GeneratesResumeDocuments` trait pattern.
