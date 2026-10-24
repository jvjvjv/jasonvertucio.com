## Why

Every save path for the three generated document types eagerly regenerates DOCX and PDF, whether or not anyone ever downloads the result: `ResumeEditCandidateService::approve()`, `Admin\ResumeEditorController::saveResumeData()` ("Always regenerate documents on save"), `TargetedResumeService::saveTailoredResume()`/`updateTailoredMarkdown()`, and `Admin\CoverLetterController`'s save flow all call `generateDocx()` then `generatePdf()` unconditionally. There is no staleness tracking anywhere in this pipeline — every write just re-renders both formats, discarding whatever was there, on the assumption that renders are cheap enough not to matter. `document_downloads` (added ahead of this proposal, see `app/Models/DocumentDownload.php`) now gives a way to find out whether that assumption holds: if a resume version, targeted resume, or cover letter is approved/saved and never downloaded, the DOCX/PDF rendered for it were wasted work. Rendering a document only when it is first requested — and reusing what's already there for a configurable window afterward — does the work exactly when it's known to be needed, and the download log this change wires in finally answers "is anyone downloading these anyway?"

## What Changes

- **BREAKING**: Remove eager DOCX/PDF generation from every save/approve path: `ResumeEditCandidateService::approve()`, `Admin\ResumeEditorController::saveResumeData()`, `TargetedResumeService::saveTailoredResume()` and `updateTailoredMarkdown()`, and `Admin\CoverLetterController`'s save flow. Each of these instead clears the affected document's stored `docx_path`/`pdf_path` (deleting the stale file, if one exists) so the next download request generates fresh content — the file is invalidated at write time, not regenerated at write time.
- Add generate-if-missing logic to all five download endpoints — `ResumeController::downloadDocx()`/`downloadPdf()`, `Admin\TargetedResumeController::download()`, `Admin\CoverLetterController::downloadDocx()`/`downloadPdf()` — so a request for a format that isn't currently cached renders it synchronously (via the existing `generateDocx()`/`generatePdf()` on each document type's service, unchanged internally) before serving it. DOCX and PDF continue to be generated independently of each other, consistent with `document-pdf-rendering`'s existing "PDF is rendered from its own body source" requirement.
- Fill the one existence-check gap this surfaces: `GeneratesResumeDocuments` has `docxExistsForCurrentVersion()`/`getLatestDocxPath()` but no PDF equivalent — add `pdfExistsForCurrentVersion()` alongside the existing `getLatestPdfPath()` so the main resume's PDF download can check "is there already a cached file" the same way its DOCX download does.
- Every download — of any of the three document types, in either format — is logged to `document_downloads` (`resume_id`/`targeted_resume_id`/`cover_letter_id`, `type`, `served_cached_document`, and a new `ip_address` column this change adds). `served_cached_document` records whether the request hit an already-generated file or triggered a fresh render.
- Add a configurable retention policy, applied uniformly across all three document types: `resume.document_retention_mode` is `cache` (default) or `delete_after_serve`.
  - `cache`: a generated file is left in place and reused by later downloads for `resume.document_retention_hours` (default 12) after it was generated; a scheduled command sweeps and deletes (nulling the stored path) files past that window.
  - `delete_after_serve`: the file is deleted and its stored path nulled immediately after the response finishes streaming, so every download after the first re-renders. No sweep needed in this mode.
- `Admin\TargetedResumeController::regenerate()`'s "Regenerate" action changes meaning: instead of rendering immediately, it invalidates the cached files (same clear-on-write behavior as a save), and the next download does the actual rendering.
- On-demand generation failing at download time is reported as a download failure (an error page/response, not a silent 404) rather than the current "document generation failed" messaging that used to appear at save/approve time — parallel to `document-pdf-rendering`'s existing "A rendering failure is reported" requirement, extended to cover DOCX failures the same way.

## Capabilities

### New Capabilities

- `on-demand-document-generation`: Defines that a document's DOCX/PDF is rendered at first download request rather than at save/approve time, reused for a configurable window (or discarded immediately after serving) rather than kept indefinitely, and that every download — of any document type, in either format — is logged with enough detail to tell whether it was served from cache or freshly rendered.

### Modified Capabilities

- `ai-persona-resume-editing`: The "Approving a candidate materializes it as the new live resume version" requirement currently states approval "regenerate[s] the resume's DOCX and PDF artifacts" and includes a scenario where "the user is shown the generation error instead of a silent failure" on regeneration failure. Both go away: approval no longer generates anything — it invalidates whatever documents existed for the resume, and a generation failure (if any) now surfaces at the next download instead of at approval time.
- `resume-candidate-review-mcp-tools`: The approve tool's requirement says it delegates "DOCX/PDF regeneration" to the same service logic as the web form. That delegation no longer exists — approval via the tool invalidates cached documents exactly as the web form does, and generates nothing synchronously.
- `targeted-resume-manual-editing`: The "Saving a manual edit persists content and regenerates artifacts" requirement, and its "Document regeneration fails after a manual save" scenario, no longer hold — saving invalidates the cached DOCX/PDF instead of regenerating them, and there is no synchronous regeneration left to fail.

## Impact

- `app/Services/ResumeEditCandidateService.php` — `approve()` no longer calls `generateDocx()`/`generatePdf()`; invalidates the new version's cached documents instead.
- `app/Http/Controllers/Admin/ResumeEditorController.php` — `saveResumeData()` drops its "always regenerate" step for the same reason.
- `app/Services/TargetedResumeService.php` — `saveTailoredResume()` and `updateTailoredMarkdown()` invalidate instead of regenerating; both currently `throw` on generation failure, which goes away with the synchronous call.
- `app/Http/Controllers/Admin/CoverLetterController.php` — its `generateDocuments()` save-time helper is replaced with invalidation; `downloadDocx()`/`downloadPdf()` gain generate-if-missing and download logging.
- `app/Http/Controllers/Admin/TargetedResumeController.php` — `download()` gains generate-if-missing and download logging; `regenerate()`'s behavior changes to invalidate-only.
- `app/Http/Controllers/ResumeController.php` — `downloadDocx()`/`downloadPdf()` gain generate-if-missing (PDF currently 404s if missing; DOCX already checks existence via `getLatestDocxPath()`) and now log through `DocumentDownload` instead of (or alongside) the existing `ResumeDownload`.
- `app/Services/Concerns/GeneratesResumeDocuments.php` — add `pdfExistsForCurrentVersion()`.
- `app/Models/DocumentDownload.php` — new `ip_address` column/fillable entry; a Laravel 13 `Attribute::make()`-based accessor where real accessor logic exists (see design.md).
- New migration adding `ip_address` to `document_downloads`.
- `config/resume.php` — new `document_retention_mode` / `document_retention_hours` keys.
- New scheduled artisan command sweeping expired cached documents in `cache` mode (registered in `routes/console.php` per this app's Laravel 13 structure).
- Models `ResumeVersion`, `TargetedResume`, `CoverLetter` — the write paths that currently set `docx_path`/`pdf_path` on successful generation now also need a clear/invalidate path.
