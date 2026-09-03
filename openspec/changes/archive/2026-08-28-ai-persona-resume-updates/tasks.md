## 1. Data model

- [x] 1.1 Confirm the resume-edit tool(s) reuse the existing `edit-resume` permission (no new permission needed — confirmed with the site owner)
- [x] 1.2 Create migration `create_resume_edit_candidates_table`: `base_resume_version_id` (FK → `resume_versions`, restrict on delete), `revision_number` (unsigned int), `status` (string, default `pending`, values `pending`/`approved` only — rejection deletes the row), `snapshot` (json), `ai_conversation_id` (nullable FK → `ai_conversations`, null on delete), `batch_started_at`, `last_edited_at`, `approved_at` (nullable), `approved_by_user_id` (nullable FK → `users`, null on delete), timestamps — note: `approved_by_user_id` is `foreignUuid`, not `foreignId` (`users.id` is a UUID in this app)
- [x] 1.3 Run the migration against both the app DB and the `wink` test DB (per project convention)
- [x] 1.4 Add `ResumeEditCandidate` model (casts, relations to `ResumeVersion`, `AiConversation`, `User`) and a factory
- [x] 1.5 Add config value for the batching window (default 12 hours), e.g. `config('resume.ai_edit_batch_window_hours')`

## 2. Snapshot shape and shared materialization logic

- [x] 2.1 ~~Extract~~ **Not needed as scoped.** `DatabaseResumeDataService::saveAllEditableData()` already operates on whatever version `getCurrentVersion()` resolves at call time; approval calls `versionService->setVersion($next)` (which flips `is_current`) immediately before `saveAllEditableData($candidate->snapshot)`, so the existing method materializes into the new version with zero changes. No extraction was needed.
- [x] 2.2 The canonical snapshot shape is exactly `ResumeDataServiceContract`'s existing shape (`personal`, `skills`, `experience`, `education`, `projects`) — the same shape `GetResumeDataTool` already returns to the chat bot. Technical profile categories are out of scope: the human admin editor itself doesn't manage them through this save path, so the persona tool doesn't either.
- [x] 2.3 ~~Not needed as scoped.~~ A candidate's base version is always the live version at creation time, so `getAllEditableData()` (which already reads the current version) is sufficient to seed it — no parameterized variant required.

## 3. Candidate resolution service

- [x] 3.1 Add `ResumeEditCandidateService` (or similar) with a method to resolve-or-create the target candidate for a given base version and edit: reuse the highest-revision `pending` candidate if its `last_edited_at` is within the batch window, else create a new one at `revision_number` + 1 seeded from that candidate's data (leaving it `pending`, not auto-resolved — it now needs an explicit human rejection later)
- [x] 3.2 Add a method to apply a partial edit (e.g. "update this experience entry," "change the summary") onto a candidate's `snapshot`
- [x] 3.3 Unit test the resolve-or-create branching: first edit ever, edit within window, edit after window (confirming the superseded candidate stays `pending`, not deleted or otherwise resolved), edit after prior candidate approved, edit after prior candidate rejected

## 4. MCP tool(s) for persona resume edits

- [x] 4.1 Add an authorization base (mirroring `AuthorizedResumeTool`) gated on the user holding `edit-resume`, with `shouldRegister()`/`guard()` — **see note below on the `AiSystem.allowed_tools` gate**
- [x] 4.2 Implement a single `update-resume-section` tool under `app/Services/Mcp/Tools/ChatBot/ResumeEdit/` (accepts `section` + JSON-encoded `data` for that section, matching `SaveTailoredResumeTool`'s precedent of a string payload rather than a deeply-typed nested schema), calling into the section-3 services
- [x] 4.3 No extra wiring needed: `AppServiceProvider::boot()` already registers the whole `app/Services/Mcp/Tools` tree (`CodeTalkerServiceProvider::addToolDirectory`), so the new tool is auto-discovered like the existing `ChatBot/*` tools
- [x] 4.4 On successful edit, append an `AiConversationMessage` tagged `metadata.origin = 'ai_resume_edit'` summarizing the change, without triggering a synchronous extra agent turn
- [x] 4.5 Log request/guard/success/failure at each stage, following `SaveTailoredResumeTool`'s logging pattern

  **Design correction found during implementation:** `Jvjvjv\CodeTalker\Services\Mcp\ChatBotToolRegistry` (vendor) filters tools by `AiSystem::allowed_tools` name list, but its `discoverHandlers()` never calls `shouldRegister()` — that hook is documented in the existing `AuthorizedResumeTool` as consulted only for a hypothetical future *external* MCP server, not this app's local chat loop. So the `edit-resume` permission gate is enforced only via `guard()` at call time (same as the already-shipped `save-resume`-gated targeted-resume tools) — the tool name is still visible in the tool list sent to the model, it just errors if called by an unauthorized user. The `allowed_tools` gate (gate 1) does filter the list, since that's plain name-matching the vendor registry already performs. The spec's "hidden entirely" wording for gate 2 doesn't hold given this architecture; it now means "unusable," matching real precedent, not "absent from the list."

## 5. Block manual edits while a candidate is pending

- [x] 5.1 In `ResumeEditorController::update()` check for any `pending` `resume_edit_candidates` row for the live version and refuse the save (409) if one exists
- [x] 5.2 Surface this state in the Inertia editor UI (banner + disabled Save FAB) with a link to review each pending candidate

## 6. Editor page candidate review and approve/reject actions

- [x] 6.1 No separate preview route existed to extend — extended the existing `GET /admin/resume/editor` (already `auth`+`can:edit-resume`-gated) to accept `?revision=`
- [x] 6.2 Candidate viewing inherits the route's existing `can:edit-resume` middleware; renders the candidate's snapshot through the same `resume/Editor` Inertia page/tabs used for live data
- [x] 6.3 Show a staleness warning when the candidate's `base_resume_version_id` no longer matches the live `is_current` version
- [x] 6.4 Add `POST /admin/resume/candidates/{candidate}/approve`: bumps to a new patch version via `DatabaseResumeVersionService::setVersion()`, sets `is_current`, materializes the snapshot via `saveAllEditableData()` (all inside one `DB::transaction`), then regenerates DOCX/PDF, then marks the candidate `approved` with `approved_at`/`approved_by_user_id`
- [x] 6.4a **Added after initial implementation** (site owner request): approving a candidate also permanently rejects every other `pending` candidate for the same base resume version, inside the same transaction — closes a data-clobbering gap where two pending siblings would compute the same next-patch version number
- [x] 6.5 Add `POST /admin/resume/candidates/{candidate}/reject`: **permanently deletes** the candidate row; UI confirms irreversibility before submitting
- [x] 6.6 Approve/Reject both reject (422-style redirect) a candidate that is not `pending`
- [x] 6.7 DOCX/PDF regeneration runs after the transaction commits; a failure there still leaves the new version live and returns the error as a flash message instead of rolling back
- [x] 6.8 The editor page lists all `pending` candidates for the live base version in a banner with per-candidate review links

## 7. Tests

- [x] 7.1 Tested the `edit-resume` permission gate directly (anonymous caller, authenticated-but-unpermitted caller, authorized caller) in `UpdateResumeSectionToolTest`. The `AiSystem.allowed_tools` gate is pure vendor-package name-filtering with no host-app logic to unit test, so it wasn't separately exercised — see the design-correction note in section 4.
- [x] 7.2 Feature test: authorized persona edit creates a candidate without changing the live resume
- [x] 7.3 Feature test: second edit within the window updates the same candidate; edit after the window creates revision 2 seeded from revision 1's data, leaving revision 1 `pending`
- [x] 7.4 Feature test: edit after approval branches from the new live version; edit after rejection branches a fresh candidate from the (unchanged) live version
- [x] 7.5 Feature test: manual admin save is refused while any candidate is `pending` for that version, and allowed again once resolved
- [x] 7.6 Not separately tested — candidate viewing uses the same `can:edit-resume` route middleware already covered by this app's existing auth tests; `non_admin_cannot_approve_or_reject` exercises the same middleware for the mutating actions.
- [x] 7.7 Feature test: approve materializes a new live version, bumps `is_current`, and marks the candidate approved (via `ResumeEditCandidateServiceTest` and the controller-level `ResumeEditCandidateReviewTest`)
- [ ] 7.8 **Not implemented.** Testing the DOCX-regeneration-failure branch requires mocking `shell_exec()`/the Node.js script, which no existing test in this codebase does; left as a follow-up rather than adding a new mocking seam under this session's scope.
- [x] 7.9 Feature test: reject permanently deletes the candidate row, leaves the live resume untouched, and unblocks manual edits / future persona edits
- [x] 7.10 Feature test: the window-elapsed case asserts the superseded candidate stays `pending` (not auto-resolved)
- [x] 7.11 Feature test: conversation transcript records the tagged `ai_resume_edit` message after a successful edit
- [x] 7.12 Feature test: approving a candidate permanently rejects every other pending candidate for the same base version, leaving pending candidates on a different base version untouched

## 8. Documentation

- [x] 8.1 Updated `CLAUDE.md`'s Resume System section to mention the AI-persona edit-candidate/approval workflow
