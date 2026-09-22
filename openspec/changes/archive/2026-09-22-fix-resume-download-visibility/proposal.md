## Why

`on-demand-document-generation` moved rendering from save-time to first-download-time, but the three download UIs were never updated to match: the resume page's floating download button, the standalone `/resume/download` page, and the targeted-resume chat interface's resume/cover-letter download icons all still gate their download links on a pre-existing `docx_path`/`pdf_path` (or `docxExists`/`pdfExists`) check. Since nothing renders a file automatically anymore, that check is now false by default, so the download affordances never appear even though the download endpoints themselves (`ensureDocx()`/`ensurePdf()`) already render on demand and would serve a file if the link existed. Visitors and the admin operating the chat interface have no way to trigger a download.

## What Changes

- Resume page (`/resume`) floating download button always offers DOCX and PDF, instead of only when a file already exists on disk.
- Standalone `/resume/download` page always offers both formats instead of showing "No resume files are currently available for download" whenever nothing has been pre-rendered.
- Targeted-resume chat interface (`BuilderChatPanel`) always offers DOCX/PDF download icons for a finalized targeted resume and for a finalized cover letter, instead of only when `docx_path`/`pdf_path` is already set on the model.
- Each affordance still only appears once the underlying document exists in a finalized/saveable state (e.g., a live resume version exists, a targeted resume or cover letter has been finalized) — the change removes the *rendered-file-exists* gate, not the *document-exists* gate.
- Clicking a download link that triggers a render failure surfaces the existing failure handling (404 / redirect with an error) unchanged; no new error UI is introduced.

## Capabilities

### New Capabilities

(none)

### Modified Capabilities

- `on-demand-document-generation`: adds a requirement that a document's download affordance (button/link) in the UI is presented based on whether the document itself exists (live resume, finalized targeted resume, finalized cover letter), not on whether a DOCX/PDF has already been rendered and cached for it.

## Impact

- `resources/views/resume/index.blade.php`, `resources/views/components/resume/download-fab.blade.php`, `resources/views/resume/download/index.blade.php`
- `app/Http/Controllers/ResumeController.php` (`index()`, `download()` — what they pass as `docxExists`/`pdfExists`/`docx_exists`/`pdf_exists`)
- `resources/js/admin/pages/resume/targeted/BuilderChatPanel.tsx` (resume and cover-letter download icon gating)
- No changes to `ensureDocx()`/`ensurePdf()`, retention, invalidation, or logging behavior — those already work correctly and are unaffected.
