## 1. Data model and configuration

- [x] 1.1 Create migration adding `ip_address` (nullable `string(45)`) to `document_downloads`, and run it against both the app database and the `wink` test database (`DB_DATABASE=wink php artisan migrate`), per this repo's dual-database convention. Add `ip_address` to `App\Models\DocumentDownload::$fillable`.
- [x] 1.2 Add a `document()` computed accessor to `App\Models\DocumentDownload` using `Illuminate\Database\Eloquent\Casts\Attribute` (design.md - Decision 6), resolving whichever of `resume`/`targetedResume`/`coverLetter` is set. Verify a unit test asserts `$download->document` returns the correct model for a resume-, targeted-resume-, and cover-letter-typed row, and `null` for none set.
- [x] 1.3 Add `document_retention_mode` (`cache`|`delete_after_serve`, default `cache`, env `RESUME_DOCUMENT_RETENTION_MODE`) and `document_retention_hours` (default `12`, env `RESUME_DOCUMENT_RETENTION_HOURS`) to `config/resume.php`, documented on the same rule as the file's other keys. Verify `php artisan config:show resume` lists both with their defaults.

## 2. Shared existence and invalidation

- [x] 2.1 Create `App\Models\Concerns\HasGeneratedDocuments` trait with `docxExists()`, `pdfExists()`, `invalidateDocx()`, `invalidatePdf()`, `invalidateDocuments()` per design.md - Decision 2. Verify a unit test using an anonymous class or one of the three models asserts: `invalidateDocx()` deletes an existing file and nulls the column; it is a no-op (no exception) when the column is already null; `invalidateDocuments()` calls both.
- [x] 2.2 Apply the trait to `App\Models\ResumeVersion`, `App\Models\TargetedResume`, `App\Models\CoverLetter`. Remove `TargetedResume`'s and `CoverLetter`'s now-duplicate local `docxExists()`/`pdfExists()` methods. Verify existing tests referencing `docxExists()`/`pdfExists()` on these three models still pass unchanged.
- [x] 2.3 Add `pdfExistsForCurrentVersion()` to `App\Services\Concerns\GeneratesResumeDocuments`, mirroring the existing `docxExistsForCurrentVersion()`/`getLatestDocxPath()` pattern against `getLatestPdfPath()`. Verify a unit test asserts it returns `true`/`false` matching whether the current version's PDF file exists.

## 3. Generate-if-missing

- [x] 3.1 Add `isCurrentlyValid(?string $path): bool` (design.md - Decision 3/4) as a small shared helper — a trait or a standalone service used by all three document services — implementing both retention modes: `delete_after_serve` checks existence only; `cache` additionally checks the file's mtime against `config('resume.document_retention_hours')`. Verify unit tests cover: missing path → `false`; existing path within the cache window → `true`; existing path past the cache window → `false`; existing path in `delete_after_serve` mode → `true` regardless of age.
- [x] 3.2 Add `ensureDocx()`/`ensurePdf()` to `App\Services\Concerns\GeneratesResumeDocuments` (for the main resume), returning `array{success: bool, path?: string, error?: string, served_cached_document: bool}` per design.md - Decision 3. Verify unit tests: a currently-valid existing file short-circuits without calling `generateDocx()`/`generatePdf()` (`served_cached_document: true`); a missing or expired file calls through to generation (`served_cached_document: false`); a generation failure propagates `success: false` and its `error`.
- [x] 3.3 Add the same `ensureDocx()`/`ensurePdf()` methods to `App\Services\TargetedResumeDocumentService`, taking `TargetedResume $targetedResume`. Verify the same three cases as 3.2, against a `TargetedResume`.
- [x] 3.4 Add the same `ensureDocx()`/`ensurePdf()` methods to `App\Services\CoverLetterDocumentService`, taking `CoverLetter $coverLetter`. Verify the same three cases as 3.2, against a `CoverLetter`.

## 4. Download logging

- [x] 4.1 Create `App\Services\DocumentDownloadLogger` with `log(ResumeVersion|TargetedResume|CoverLetter $document, string $type, bool $servedCachedDocument, string $ipAddress): DocumentDownload` per design.md - Decision 5. Verify a unit test asserts it creates a `DocumentDownload` row with exactly the correct one of `resume_id`/`targeted_resume_id`/`cover_letter_id` set for each of the three document types, plus the given `type`, `served_cached_document`, and `ip_address`.

## 5. Wire the download endpoints

- [x] 5.1 Update `ResumeController::downloadDocx()` and `downloadPdf()` (`app/Http/Controllers/ResumeController.php`) to call `ensureDocx()`/`ensurePdf()` instead of checking `getLatestDocxPath()`/`getLatestPdfPath()` directly and 404ing if missing; on failure, return the existing 404/error response shape with the generation error instead of the generic "not available" message; on success, log via `DocumentDownloadLogger` (in addition to the existing `ResumeDownload::record()` call — design.md, Decision 1) and set `deleteFileAfterSend()` on the response when `config('resume.document_retention_mode') === 'delete_after_serve'`, nulling `docx_path`/`pdf_path` via `forceFill(...)->save()` (not `invalidateDocx()`) at that point. Verify a feature test downloads the DOCX and PDF with no pre-generated file present and asserts both succeed, are logged, and (in `cache` mode) leave the file on disk; a second test in `delete_after_serve` mode asserts the file is gone after the response and the column is null.
- [x] 5.2 Update `Admin\TargetedResumeController::download()` the same way, using `TargetedResumeDocumentService::ensureDocx()`/`ensurePdf()`. Verify the equivalent feature tests from 5.1 against a `TargetedResume`.
- [x] 5.3 Update `Admin\CoverLetterController::downloadDocx()`/`downloadPdf()` the same way, using `CoverLetterDocumentService::ensureDocx()`/`ensurePdf()`, replacing the current "Save the cover letter to regenerate it" redirect-on-missing-file behavior. Verify the equivalent feature tests from 5.1 against a `CoverLetter`.

## 6. Invalidate instead of regenerate on write

- [x] 6.1 In `ResumeEditCandidateService::approve()` (`app/Services/ResumeEditCandidateService.php:117-124`), replace the `generateDocx()`/`generatePdf()` calls and their error-branch return values with a single call to the new current version's `invalidateDocuments()`; `approve()` now always returns `['success' => true]`. Verify a feature test asserts the resolved current `ResumeVersion` has null `docx_path`/`pdf_path` after approval, and that `ResumeVersionServiceContract::generateDocx()`/`generatePdf()` are never called during approval (mock/spy and assert zero calls).
- [x] 6.2 In `Admin\ResumeEditorController::saveResumeData()`, replace its "Always regenerate documents on save" step with the same `invalidateDocuments()` call. Verify the existing feature test for this route still passes, updated to assert invalidation instead of regeneration.
- [x] 6.3 In `TargetedResumeService::saveTailoredResume()` and `updateTailoredMarkdown()` (`app/Services/TargetedResumeService.php`), replace the `generateDocx()`/`generatePdf()` calls (and `saveTailoredResume()`'s throw-on-failure, and `updateTailoredMarkdown()`'s failure-array return) with `$targetedResume->invalidateDocuments()`. Verify feature tests assert both methods persist their data and invalidate any previously-set `docx_path`/`pdf_path`, without calling `TargetedResumeDocumentService::generateDocx()`/`generatePdf()`.
- [x] 6.4 In `Admin\CoverLetterController`, replace `generateDocuments()`'s body (or its two call sites directly) with `$coverLetter->invalidateDocuments()`. Verify feature tests assert a cover letter save invalidates any previously-set `docx_path`/`pdf_path` without calling `CoverLetterDocumentService::generateDocx()`/`generatePdf()`.
- [x] 6.5 Update `Admin\TargetedResumeController::regenerate()` to call `invalidateDocuments()` instead of generating immediately (design.md - proposal.md "What Changes"). Verify a feature test asserts the action clears the cached files without producing new ones, and that a subsequent download regenerates them.

## 7. Retention sweep

- [x] 7.1 Create `resume:sweep-expired-documents` artisan command: for each of `ResumeVersion`, `TargetedResume`, `CoverLetter`, find rows with a non-null `docx_path`/`pdf_path` whose file's mtime is older than `config('resume.document_retention_hours')`, and call `invalidateDocx()`/`invalidatePdf()` on each. Verify a feature test seeds an expired and a non-expired file per model type and asserts only the expired ones are invalidated.
- [x] 7.2 Register it in `routes/console.php` via `Schedule::command('resume:sweep-expired-documents')->hourly()->withoutOverlapping()`, alongside the existing `Schedule::command(...)` entries. Verify `php artisan schedule:list` includes it.

## 8. Spec-aligned verification

- [x] 8.1 Run `php artisan test --compact --filter=Resume` and `--filter=Document` (or the equivalent covering `ResumeController`, `Admin\TargetedResumeController`, `Admin\CoverLetterController`, `ResumeEditCandidateService`, `TargetedResumeService`, and the new services/trait) and confirm all pass.
- [x] 8.2 Run the full suite (`vendor/bin/phpunit`) and confirm no regression beyond the pre-existing `AdminNavigationServiceTest::test_no_unflagged_non_admin_routes_in_navigation` failure.
- [x] 8.3 Run `openspec validate generate-documents-on-demand --strict` and confirm it passes with no errors.
