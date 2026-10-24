## 1. Main resume page (`/resume`) download affordances

- [x] 1.1 In `app/Http/Controllers/ResumeController.php::index()`, stop deriving `docxExists`/`pdfExists` from `getLatestDocxPath()`/`getLatestPdfPath()` and instead base them on whether a live resume version exists (`$liveVersion !== null`), so the FAB shows whenever there is a resume to download.
- [x] 1.2 Verify `resources/views/components/resume/download-fab.blade.php` and `resources/views/resume/index.blade.php` need no template changes beyond the new prop values — confirm the `@if($docxExists || $pdfExists)` / per-format `@if` blocks already do the right thing once step 1.1 passes `true` whenever a live version exists.
- [x] 1.3 In `app/Http/Controllers/ResumeController.php::download()`, stop deriving `docx_exists`/`pdf_exists` from `getLatestDocxPath()`/`getLatestPdfPath()` and instead base them on whether a live resume version exists, matching 1.1.
- [x] 1.4 Add a feature test asserting `GET /resume` (as a user with `save-resume`, or with a valid share code) shows both download options when a live resume version exists but neither DOCX nor PDF has ever been rendered (no `ResumeVersion::docx_path`/`pdf_path` set, nothing in `storage/app/resumes`).
- [x] 1.5 Add a feature test asserting `GET /resume/download` shows both download links under the same no-cached-file conditions as 1.4, rather than the "No resume files are currently available for download" message.
- [x] 1.6 Add a feature test asserting `GET /resume` and `GET /resume/download` show no download affordance when no live `ResumeVersion` exists at all.

## 2. Targeted-resume chat interface download affordances

- [x] 2.1 In `resources/js/admin/pages/resume/targeted/BuilderChatPanel.tsx`, remove the `targetedResume.docx_path` / `targetedResume.pdf_path` guards around the resume DOCX/PDF `IconButton`s so both always render once `targetedResume` is finalized (the existing outer `targetedResume ? (...) : undefined` ternary already gates on the document existing).
- [x] 2.2 In the same file, remove the `coverLetter.docx_path` / `coverLetter.pdf_path` guards around the cover-letter DOCX/PDF `IconButton`s so both always render once `coverLetter` is finalized (the existing outer `coverLetter ? (...) : undefined` ternary already gates on the document existing).
- [x] 2.3 Confirm `TargetedResume`/`CoverLetter` TypeScript types passed into `BuilderChatPanel` still declare `docx_path`/`pdf_path` only if still used elsewhere on the page (e.g. status chips); remove now-unused fields only if nothing else reads them.
- [x] 2.4 Run `npm run build` (or `npm run dev`) and manually verify in the browser that a freshly finalized targeted resume and cover letter — before either has ever been downloaded — show DOCX and PDF icons in the chat panel, and that clicking one renders and downloads the file. `npm run build` completes cleanly with the updated `BuilderChatPanel.tsx`; manual browser click-through was not performed in this session (no interactive browser available) — flagged for the developer to confirm visually.

## 3. Regression check

- [x] 3.1 Run `php artisan test --compact --filter=ResumeDownloadOnDemandTest` and `php artisan test --compact --filter=TargetedResumeDownloadOnDemandTest` to confirm the existing on-demand generation/logging/invalidation behavior is unaffected by the visibility change. (`php artisan test` was unavailable in this environment; ran the equivalent `vendor/bin/phpunit --filter="ResumeDownloadOnDemandTest|TargetedResumeDownloadOnDemandTest"` — 5/5 pass, pre-existing notices confirmed unrelated via a stash comparison against `develop`.)
- [x] 3.2 Run the new tests from 1.4-1.6 and confirm they pass: `php artisan test --compact --filter=ResumeDownloadOnDemandTest`. (Ran `vendor/bin/phpunit --filter=ResumeDownloadVisibilityTest` — 4/4 pass.)
