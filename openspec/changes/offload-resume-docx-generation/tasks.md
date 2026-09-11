## 1. Database

- [ ] 1.1 Create migration adding `document_generation_status` (string, default `pending`) and `document_generation_error` (nullable text) to `resume_versions`, and run it against both the app database and the `wink` test database (`DB_DATABASE=wink php artisan migrate`), per this repo's dual-database convention.
- [ ] 1.2 Add `document_generation_status` and `document_generation_error` to `ResumeVersion::$fillable` in `app/Models/ResumeVersion.php`. Verify via `php artisan tinker --execute` that a `ResumeVersion` can be mass-updated with both fields.

## 2. Event and Listener

- [ ] 2.1 Create `App\Events\ResumeVersionApproved` (plain event, `Dispatchable`, carrying `ResumeVersion $version` and `string $approvedByUserId`) per design.md - Decision 1.
- [ ] 2.2 Create `App\Mail\ResumeDocumentGenerationFailed` (`ShouldQueue` Mailable, parallel to `App\Mail\CommentReceivedMail`) carrying the `ResumeVersion` and the failure error string, with a markdown view under `resources/views/mail/`. Verify with a `preview()` static method matching the existing `ResumeUpdated::preview()`/`CommentReceivedMail::preview()` pattern.
- [ ] 2.3 Create `App\Listeners\GenerateResumeDocuments` (`implements ShouldQueue`) that calls `ResumeVersionServiceContract::generateDocx()` then `generatePdf()` (only if DOCX succeeded), updates the event's `ResumeVersion` with `document_generation_status`/`document_generation_error` on success or failure, and on failure sends `ResumeDocumentGenerationFailed` to `config('comments.notification_email')` — per design.md - Decision 2.
- [ ] 2.4 Register the listener in `App\Providers\AppServiceProvider::boot()` via `Event::listen(ResumeVersionApproved::class, GenerateResumeDocuments::class)`, alongside the existing `FlushBlogFeedCache` registration.

## 3. Service changes

- [ ] 3.1 In `App\Services\ResumeEditCandidateService::approve()` (`app/Services/ResumeEditCandidateService.php:92-129`), remove the synchronous `generateDocx()`/`generatePdf()` calls and the `$docxResult`/`$pdfResult` error branches; after the existing `DB::transaction()` block commits, resolve the new current version via `ResumeVersion::current()->first()` and dispatch `ResumeVersionApproved::dispatch($newVersion, $approvedByUserId)`; simplify the method to always `return ['success' => true]`.
- [ ] 3.2 Write/update a feature test for `ResumeEditCandidateService::approve()` (`Queue::fake()` or `Event::fake([ResumeVersionApproved::class])`) asserting: the candidate is approved and materialized as before, `ResumeVersionApproved` is dispatched with the correct version and approving user id after commit, and `approve()` returns `['success' => true]` without calling `ResumeVersionServiceContract::generateDocx()`/`generatePdf()` synchronously (mock/spy the contract and assert zero calls within the request).
- [ ] 3.3 Write a feature test for `GenerateResumeDocuments` asserting: on `generateDocx()`/`generatePdf()` both succeeding, the version's `document_generation_status` becomes `succeeded` and `document_generation_error` is null, no mail sent; on either failing, `document_generation_status` becomes `failed`, `document_generation_error` is populated, and `ResumeDocumentGenerationFailed` is sent to `config('comments.notification_email')` (`Mail::fake()` + `assertSent`).

## 4. Caller updates

- [ ] 4.1 Update `Admin\ResumeEditorController::approveCandidate()` (`app/Http/Controllers/Admin/ResumeEditorController.php:211-243`) to drop the `isset($result['error'])` branch — the flashed message is now always the success message ("Candidate approved and is now the live resume."). Verify existing feature tests for this route still pass and update any test asserting on the old failure-message branch.
- [ ] 4.2 Update `ApproveResumeCandidateTool::handle()` (`app/Services/Mcp/Tools/ChatBot/ResumeEdit/ApproveResumeCandidateTool.php:49-88`) to drop the `isset($result['error'])` branch — the tool's structured response is now always the success payload. Verify existing feature/unit tests for this tool still pass and update any test asserting on the old "document generation failed" error response.

## 5. Spec-aligned verification

- [ ] 5.1 Run `vendor/bin/phpunit --testsuite=Feature --filter=Resume` (or the equivalent filtered run covering `ResumeEditCandidateService`, `ResumeEditorController`, and `ApproveResumeCandidateTool` tests) and confirm all pass.
- [ ] 5.2 Manually verify (or write a feature test for) the queue-restart requirement is documented, not code-tested: confirm `CLAUDE.md` - Queues already states the `supervisorctl restart` requirement, and note in the PR description that a deploy of this change requires a worker restart.
- [ ] 5.3 Run `openspec validate --change offload-resume-docx-generation --strict` and confirm it passes with no errors.
