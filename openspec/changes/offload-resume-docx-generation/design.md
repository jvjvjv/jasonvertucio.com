## Context

See `proposal.md` - Why. Today `ResumeEditCandidateService::approve()` (`app/Services/ResumeEditCandidateService.php:92-129`) publishes the new live version inside a `DB::transaction()`, then, after commit, synchronously calls `ResumeVersionServiceContract::generateDocx()` and `generatePdf()` (implemented by the `App\Services\Concerns\GeneratesResumeDocuments` trait on `DatabaseResumeVersionService` — `generateDocx()` renders the DOCX in PHP via `DocumentRenderer`, while `generatePdf()` `exec()`s `libreoffice --headless --convert-to pdf`), and returns any generation failure to the caller. There are two callers: `Admin\ResumeEditorController::approveCandidate()` (`app/Http/Controllers/Admin/ResumeEditorController.php:211-243`) and `ApproveResumeCandidateTool::handle()` (`app/Services/Mcp/Tools/ChatBot/ResumeEdit/ApproveResumeCandidateTool.php:49-88`), both of which branch on `$result['error']` to show a "document generation failed" message.

The app already has one queued job precedent: `CommentReceivedMail` (`ShouldQueue`), dispatched via `Mail::to(...)->queue(...)` in `Admin\ResumeEditorController::saveResumeData()` and consumed by `Mail::to(...)->send(...)` synchronously in `CommentObserver`. Event/listener wiring in this app is manual, not auto-discovered: `AppServiceProvider::boot()` calls `Event::listen([...], FlushBlogFeedCache::class)` for the blog-cache-flush listener. This change follows that same manual-registration convention rather than introducing auto-discovery.

`ResumeEditorController::saveResumeData()` (the *manual* admin-editor save path, lines 113-135) has its own separate inline `generateDocx()`/`generatePdf()` calls and is explicitly out of scope (see proposal.md - What Changes) — only the candidate-approval path (`ResumeEditCandidateService::approve()`) changes.

## Goals / Non-Goals

**Goals:**
- Remove the synchronous `generateDocx()`/`generatePdf()` calls from `ResumeEditCandidateService::approve()`, replacing them with a dispatched event handled by a queued listener.
- Preserve discoverability of a generation failure despite no longer returning it synchronously: record status/error on the `resume_versions` row, and notify by email.
- Keep the manual admin-editor save path (`saveResumeData()`) unchanged.

**Non-Goals:**
- Building a UI to display `document_generation_status` in the admin editor (the column is added and populated in this change; surfacing it visually in the editor/preview page is left to a follow-up change).
- Retrying failed generation automatically.
- Changing the `GeneratesResumeDocuments` trait's `generateDocx()`/`generatePdf()` implementations themselves — they are called from a new location, not modified.

## Decisions

### 1. New event: `App\Events\ResumeVersionApproved`

A plain event class (not a model event) carrying the two pieces of data a listener needs:

```php
final class ResumeVersionApproved
{
    use Dispatchable;

    public function __construct(
        public readonly ResumeVersion $version,
        public readonly string $approvedByUserId,
    ) {}
}
```

Dispatched from `ResumeEditCandidateService::approve()` immediately after the existing `DB::transaction()` closure returns (i.e., after commit — never from inside the transaction, so a queued listener picked up by a fast worker can never see the pre-commit state):

```php
DB::transaction(function () use ($candidate, $version, $approvedByUserId) {
    // ...unchanged...
});

ResumeVersionApproved::dispatch($candidate->baseResumeVersion->fresh(), $approvedByUserId);
// Actually: dispatch the *new* current version, not baseResumeVersion — see note below.

return ['success' => true];
```

Note: `approve()` does not currently hold a reference to the newly created `ResumeVersion` row (`setVersion()` creates it internally, encapsulated in `ResumeVersionServiceContract`). The event needs that row's id to update its status columns. Resolved by having `ResumeVersionServiceContract::setVersion()` continue to return `void` (unchanged interface) and instead resolving the new current version after commit via `ResumeVersion::current()->first()` — consistent with how `ApproveResumeCandidateTool` already resolves the live version the same way (`ResumeVersion::current()->first()`, line 55 of that file).

**Alternative considered**: change `setVersion()`'s return type to `ResumeVersion`. Rejected to avoid touching the shared contract (`app/Contracts/ResumeVersionServiceContract.php`) and its implementation (`DatabaseResumeVersionService`; `JsonResumeVersionService` is now an empty subclass of it, and `config/resume.php` defaults the driver to `database`) for a need local to one caller.

### 2. New queued listener: `App\Listeners\GenerateResumeDocuments`

```php
class GenerateResumeDocuments implements ShouldQueue
{
    public function __construct(private ResumeVersionServiceContract $versionService) {}

    public function handle(ResumeVersionApproved $event): void
    {
        $docxResult = $this->versionService->generateDocx();
        $pdfResult = $docxResult['success'] ? $this->versionService->generatePdf() : ['success' => false];

        if ($docxResult['success'] && $pdfResult['success']) {
            $event->version->update([
                'document_generation_status' => 'succeeded',
                'document_generation_error' => null,
            ]);
            return;
        }

        $error = ! $docxResult['success']
            ? 'DOCX generation failed: '.($docxResult['error'] ?? 'Unknown error')
            : 'PDF generation failed: '.($pdfResult['error'] ?? 'Unknown error');

        $event->version->update([
            'document_generation_status' => 'failed',
            'document_generation_error' => $error,
        ]);

        Mail::to(config('comments.notification_email'))->send(new ResumeDocumentGenerationFailed($event->version, $error));
    }
}
```

This reproduces the exact success/failure branching `approve()` has today (DOCX first, PDF only if DOCX succeeded), just moved and now writing to columns/email instead of a return array.

Registered in `AppServiceProvider::boot()` alongside the existing `FlushBlogFeedCache` registration:

```php
Event::listen(ResumeVersionApproved::class, GenerateResumeDocuments::class);
```

`GenerateResumeDocuments` does not implement `ShouldQueue` via a queued *event* — the event itself stays a plain, synchronously-dispatched event (cheap, no I/O), and only the listener is queued, matching how `CommentReceivedMail` is the queued unit today rather than a queued "comment created" event.

### 3. `resume_versions` gains `document_generation_status` and `document_generation_error`

New migration adding:
- `document_generation_status` — string, default `'pending'`, one of `pending`/`succeeded`/`failed` (enforced at the application layer, not a DB enum, consistent with how `ResumeEditCandidate::status` and `Comment` visibility flags are handled elsewhere in this codebase — plain strings/booleans, no DB-level enum constraints).
- `document_generation_error` — nullable text.

Both added to `ResumeVersion::$fillable` (`app/Models/ResumeVersion.php`). No new cast needed (plain string/nullable string).

A version starts `pending` at creation (default), moves to `succeeded` or `failed` once `GenerateResumeDocuments` runs. This mirrors `resume_edit_candidates.status`'s pattern of a small fixed string vocabulary.

### 4. Failure notification reuses `comments.notification_email`

Rather than adding a new `resume.*` config key, the failure email is sent to `config('comments.notification_email')` — the same single-operator recipient the comment-notification system already uses (`config/comments.php`). This is a personal site with one operator (`bigcartoonjay@gmail.com`/`me@jasonvertucio.com`), so a second identically-purposed "who gets operational alerts" config key would be pure duplication. `App\Mail\ResumeDocumentGenerationFailed` is a new, small `ShouldQueue` Mailable (parallel to `CommentReceivedMail`) carrying the `ResumeVersion` and the error string, sent via `Mail::send()` (not `->queue()`) from inside the already-queued listener — no need to double-queue.

**Alternative considered**: reuse the existing `ResumeUpdated` mailable. Rejected — it is addressed to resume *viewers* (`ResumeShareCode` recipients) announcing a successful update with a share link; a generation-failure alert to the site operator is a different audience and a different message, so a distinct Mailable is clearer than overloading one class for two purposes.

### 5. `approve()`'s return type simplifies

`approve(): array` keeps its `array{success: bool, error?: string}` shape for now (minimal diff to the two callers), but in practice always returns `['success' => true]` once the version-publishing transaction commits — the `error` key is dead going forward. Both callers (`ResumeEditorController::approveCandidate()`, `ApproveResumeCandidateTool::handle()`) drop their `isset($result['error'])` branches per proposal.md - Impact.

**Alternative considered**: change `approve()`'s return type to `void`. Rejected for this change to keep the diff minimal and the public method signature stable; a future cleanup change can simplify the signature once no caller depends on the array shape.

## Risks / Trade-offs

- **[Risk] Losing the synchronous "approved, but document generation failed" message a reviewer currently sees immediately** → Mitigated by recording `document_generation_status`/`document_generation_error` on the version row (durable, queryable) and emailing the operator on failure. A reviewer who wants to confirm generation succeeded now checks back rather than seeing it inline — an explicit, accepted trade-off per proposal.md - Why.
- **[Risk] Per CLAUDE.md, `queue:work` boots once and does not pick up new code after deploy** → The listener will not run (or will run the pre-deploy code) until the worker is restarted. Migration Plan below calls this out explicitly as a required deploy step, not optional.
- **[Risk] `exec()`-based LibreOffice PDF conversion failing silently if the queue worker itself is down or the job errors out unhandled** → Out of scope for this change (pre-existing risk equally present in the synchronous path today); `--tries=3` on the production worker (per CLAUDE.md) gives the job automatic retries before failing.
- **[Interaction] `replace-libreoffice-with-weasyprint` rewrites the `generatePdf()` body this change relocates** → Their surfaces are disjoint: this change moves *when* generation runs and never edits `generatePdf()`; that change rewrites *how* the PDF is produced and never states when. No file is edited by both, and per that change's `design.md`, whichever lands second rebases — neither blocks the other. The one substantive interaction is to this change's motivation: the LibreOffice `exec()` is the bulk of the latency being moved off the request path, and that change reduces it.
- **[Trade-off] The new columns are not yet surfaced in any admin UI** → Deliberately deferred (see Non-Goals); the data is queryable via tinker/DB in the interim, matching the "record it, notify on failure" resolution chosen for this change.

## Migration Plan

1. Add and run the `resume_versions` migration (`document_generation_status`, `document_generation_error`) against both the app database and the `wink` test database, per this repo's dual-database convention.
2. Deploy the code (event, listener, mailable, updated `approve()`/callers, `AppServiceProvider` registration).
3. Restart the queue worker (`sudo supervisorctl restart <program-name>`) — required, not optional, since `queue:work` holds booted code in memory (see CLAUDE.md - Queues). Skipping this means the listener silently does not exist to the running worker.
4. No backfill needed for existing `resume_versions` rows: the default `pending` status is accurate for a version whose generation outcome was never previously recorded, and none of them will be regenerated by this change.

Rollback: revert the code deploy and restart the worker again; the added columns are additive and harmless to leave in place even if the event/listener code is rolled back (they simply stop being written to).
