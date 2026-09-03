## 1. Backend: version-on-approval

- [x] 1.1 Add a version-comparison helper to `ResumeEditCandidateService` (component-wise year/major/minor, not string comparison) and a `suggestedNextVersion(ResumeVersion $base): string` method built from the existing `nextPatchVersion()` logic.
- [x] 1.2 Change `ResumeEditCandidateService::approve()` to accept a required `string $version` parameter; validate it matches `YYYY.MAJOR.MINOR` and is strictly greater than `$candidate->baseResumeVersion->version`, throwing (mirroring the existing non-pending `RuntimeException`) on failure before the transaction starts.
- [x] 1.3 Use the validated `$version` (not `nextPatchVersion()`) when calling `$this->versionService->setVersion()` inside `approve()`.
- [x] 1.4 Update/add unit tests for `ResumeEditCandidateService::approve()`: suggested-default version succeeds, a chosen higher major/minor version succeeds, an equal-or-lower version is rejected with no version created, a malformed version string is rejected, and existing approve behaviors (sibling rejection, document regen failure) still pass with the new signature.

## 2. Backend: web-form approval path

- [x] 2.1 Update `ResumeEditorController::approveCandidate()` to validate the incoming `version` request field (format + presence) and pass it to `ResumeEditCandidateService::approve()`, surfacing a validation failure the same way other approve failures are shown (flash message).
- [x] 2.2 Update `ResumeController::index()` (or wherever the approve form's default version is sourced) to pass the live version's `suggestedNextVersion()` value to the preview page so the Approve form can pre-fill it.
- [x] 2.3 Add a version text input (pre-filled with the suggested default) to the Approve form in `resources/views/resume/index.blade.php`, submitted alongside the existing `redirect_to` hidden field.

## 3. MCP tools

- [x] 3.1 Add `ListPendingResumeCandidatesTool` under `app/Services/Mcp/Tools/ChatBot/ResumeEdit/`, extending `AuthorizedResumeEditTool`, named `list-pending-resume-candidates`. It loads the live `ResumeVersion`, queries `ResumeEditCandidate::pending()` scoped to that base, and returns each candidate's revision number, last-edited timestamp, and status, plus a `suggested_version` from `ResumeEditCandidateService::suggestedNextVersion()`.
- [x] 3.2 Add `ApproveResumeCandidateTool` under the same directory, extending `AuthorizedResumeEditTool`, named `approve-resume-candidate`, with schema fields `revision_number` (required integer) and `version` (required string). It resolves the candidate via `ResumeEditCandidateService::findCandidateByRevisionNumber()` against the live version, rejects if not found or not `pending`, then calls `approve()` with the resolved `$this->context->userId` and the supplied version, translating validation exceptions into `Response::error()`.
- [x] 3.3 Record a tagged conversation message on successful approval via the tool, mirroring `UpdateResumeSectionTool::recordEditMessage()` (e.g. `origin: ai_resume_edit_approval`).
- [x] 3.4 Add feature tests for both new tools covering: hidden when `AiSystem.allowed_tools` excludes them, hidden/error for a user lacking `edit-resume`, successful list with an empty and non-empty pending set, successful approve, approve rejected for unknown/non-pending revision number, approve rejected for an invalid version.

## 4. Documentation

- [x] 4.1 Update `CLAUDE.md`'s "AI-Persona Resume Editing" section to describe the new `list-pending-resume-candidates` and `approve-resume-candidate` MCP tools and the version-on-approval behavior.
- [x] 4.2 Update `README.md`'s resume-related documentation if it enumerates MCP tools or resume routes, to mention the new tools. (No changes needed — README.md does not enumerate MCP tools or admin resume routes; only an unrelated `resume:migrate-to-db` artisan command is listed.)

## 5. Verification

- [x] 5.1 Run the updated/added PHPUnit tests (`php artisan test --compact --filter=ResumeEditCandidate` and equivalents for the new MCP tool tests and the controller feature test). All 175 resume-related tests pass (`vendor/bin/phpunit --filter="ResumeCandidateTool|Resume"`).
- [x] 5.2 Manually exercise the version-input Approve web form, and the two new MCP tools via a chat conversation whose `AiSystem` allows them, against a seeded pending candidate. Verified headlessly: the two new tools are auto-discovered by `ChatBotToolRegistry` (confirmed via tinker), the approve/reject routes are registered, and `resume.index` renders correctly with the new version input for a pending candidate. Full manual exercise in a browser against a live `AiSystem`-scoped chat conversation was not performed — recommend a quick pass in the browser before considering this fully done.
