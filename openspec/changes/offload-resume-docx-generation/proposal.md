## Why

`ResumeEditCandidateService::approve()` (`app/Services/ResumeEditCandidateService.php:100-129`) synchronously shells out to a Node.js script (`scripts/generate-resume.js`, via `JsonResumeVersionService::generateDocx()`/`generatePdf()`) to regenerate the resume DOCX and PDF, after the version-publishing `DB::transaction()` has already committed. Both callers — the admin web form (`POST /admin/resume/candidates/{candidate}/approve`) and the `approve-resume-candidate` MCP tool used by an AI persona — block their HTTP/MCP response on that slow external process. Neither caller needs generation to finish before it can tell the user "approved"; they only currently use the synchronous result to report a generation failure. Moving generation off the request path removes that latency without losing failure visibility, by recording it instead of returning it.

## What Changes

- Add a `ResumeVersionApproved` domain event, dispatched from `ResumeEditCandidateService::approve()` immediately after the `DB::transaction()` block commits, carrying the newly-current `ResumeVersion` and the approving user id.
- Add a queued listener, `GenerateResumeDocuments` (implements `ShouldQueue`), that handles the event by calling `generateDocx()` then `generatePdf()` on `ResumeVersionServiceContract`, exactly as `approve()` does today, but off the request/response cycle.
- **BREAKING**: `ResumeEditCandidateService::approve()` no longer performs DOCX/PDF generation and no longer returns a document-generation error in its result array — it now always returns `['success' => true]` once the version is published. Both callers' "document generation failed" messaging is removed from the synchronous response.
- Add two nullable columns to `resume_versions` — `document_generation_status` (`pending` \| `succeeded` \| `failed`, default `pending`) and `document_generation_error` (text) — set by the listener, so a failure is discoverable after the fact instead of only in a response that no longer carries it.
- On generation failure, the listener emails the configured recipient (reusing the existing `comments.notification_email` pattern) so a failure is not silently invisible between admin visits.
- The admin resume editor's manual-save path (`ResumeEditorController::saveResumeData()`, `app/Http/Controllers/Admin/ResumeEditorController.php:113-135`) is **not** touched by this change — it keeps its own inline `generateDocx()`/`generatePdf()` calls. Only the candidate-approval path changes.

## Capabilities

### New Capabilities

- `resume-document-generation-events`: Defines the `ResumeVersionApproved` event/listener pair, the queued asynchronous DOCX/PDF generation it performs, and the `document_generation_status`/`document_generation_error` record it leaves behind (plus the failure email) as the new way a generation failure is surfaced.

### Modified Capabilities

- `ai-persona-resume-editing`: The "Approving a candidate materializes it as the new live resume version" requirement currently states approval "regenerate[s] the resume's DOCX and PDF artifacts" synchronously and that "the user is shown the generation error instead of a silent failure" when regeneration fails. Both statements change: regeneration is now asynchronous (queued), and a synchronous approval response can no longer carry a generation error — the reviewer instead sees generation status recorded on the resume version.
- `resume-candidate-review-mcp-tools`: The "A tool approves a pending candidate..." requirement says the tool "delegate[s] ... DOCX/PDF regeneration" to the same service logic as the web form and implies the tool's response can report a generation failure. The tool's response contract changes: it no longer reports a document-generation error synchronously, since generation is now queued.

## Impact

- `app/Services/ResumeEditCandidateService.php` — `approve()` dispatches the event instead of calling `generateDocx()`/`generatePdf()`; return type simplifies.
- `app/Http/Controllers/Admin/ResumeEditorController.php` — `approveCandidate()` no longer branches on a `result['error']` key from `approve()`.
- `app/Services/Mcp/Tools/ChatBot/ResumeEdit/ApproveResumeCandidateTool.php` — drops the `isset($result['error'])` branch that reports generation failure in the tool response.
- New: `app/Events/ResumeVersionApproved.php`, `app/Listeners/GenerateResumeDocuments.php`, `app/Mail/ResumeDocumentGenerationFailed.php`.
- New migration adding `document_generation_status` and `document_generation_error` to `resume_versions`.
- `app/Models/ResumeVersion.php` — new fillable/cast attributes for the two new columns.
- `config/comments.php` (or `config/resume.php`) — reused/extended for the failure-notification recipient.
- `app/Providers/EventServiceProvider.php` or equivalent listener registration (Laravel 13's `bootstrap/app.php`/auto-discovery — confirm during design) wires the event to the listener.
- Queue worker (`queue:work`, `default` queue) now also processes resume document generation jobs; per `CLAUDE.md`, a deploy touching this code requires a `supervisorctl restart` of the worker.
