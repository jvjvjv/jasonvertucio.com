## Context

See `proposal.md` - Why. Today, every write path unconditionally regenerates both formats:

- `ResumeEditCandidateService::approve()` (`app/Services/ResumeEditCandidateService.php:117-124`) calls `generateDocx()` then `generatePdf()` on `ResumeVersionServiceContract` after its `DB::transaction()` commits.
- `Admin\ResumeEditorController::saveResumeData()` does the same, per its "Always regenerate documents on save" comment.
- `TargetedResumeService::saveTailoredResume()` (line ~458) and `updateTailoredMarkdown()` (line ~526) call `TargetedResumeDocumentService::generateDocx()`/`generatePdf()`, throwing (the former) or returning a failure array (the latter) if either fails.
- `Admin\CoverLetterController::generateDocuments()` (line 198) calls `CoverLetterDocumentService::generateDocx()`/`generatePdf()` from the controller's save actions.

Three different existence-tracking shapes exist today: `TargetedResume` and `CoverLetter` each define their own `docxExists()`/`pdfExists()` (`{format}_path !== null && file_exists({format}_path)`); `ResumeVersion` has the same `docx_path`/`pdf_path` columns but no model-level exists methods — `GeneratesResumeDocuments::docxExistsForCurrentVersion()`/`getLatestDocxPath()` do the equivalent lookup at the service layer, and there is no PDF equivalent of `docxExistsForCurrentVersion()` at all.

None of the three formats/services track *when* a file was rendered or compare it against the underlying data's freshness — "does a file exist at the stored path" is the entire staleness model today, which is only safe because generation is unconditional on every write.

The three download entry points are `ResumeController::downloadDocx()`/`downloadPdf()` (`app/Http/Controllers/ResumeController.php:202,223`), `Admin\TargetedResumeController::download()` (`app/Http/Controllers/Admin/TargetedResumeController.php:466`), and `Admin\CoverLetterController::downloadDocx()`/`downloadPdf()` (`app/Http/Controllers/Admin/CoverLetterController.php:158,178`). All five currently 404/redirect if the expected file is missing rather than generating it.

`app/Models/DocumentDownload.php` (added ahead of this change) already has the `resume_id`/`targeted_resume_id`/`cover_letter_id`/`type`/`served_cached_document` shape this design writes to; it is missing only `ip_address`.

## Goals / Non-Goals

**Goals:**
- Move rendering from every save/approve to the first download request for a given format, for all three document types.
- Make every write path that used to regenerate instead invalidate (delete file, clear stored path) so a later download can never be served content rendered before the most recent save.
- Log every download — cache hit or miss — with enough detail to evaluate whether caching is worth keeping.
- Support both a time-window cache and immediate post-serve deletion, chosen by configuration, uniformly across all three document types.

**Non-Goals:**
- Changing how a document's body/HTML/OOXML is composed. `generateDocx()`/`generatePdf()` on each service are called from a new place (a download request instead of a save), not modified internally.
- A UI for adjusting the retention mode/window at runtime — it is an env-backed config value, changed by editing `.env` and redeploying, consistent with how every other `resume.*` setting in this app works.
- Retrying a failed on-demand render automatically. A failed render fails that download request; the next download attempt tries again from scratch (nothing was cached to retry from).
- Migrating or backfilling `document_downloads` history from `ResumeDownload`'s existing rows — the two tables track different things (see below) and this change does not merge them.

## Decisions

### 1. `document_downloads` stays separate from `ResumeDownload`

`ResumeDownload` (`app/Models/ResumeDownload.php`) is specifically about *share-code-gated* main-resume viewing — it carries `share_code_id` and is written from `ResumeController::trackDownload()`, called only from the two main-resume download routes. `document_downloads` is about *which generated file was served and how* — it covers all three document types, records the format and whether the file came from cache, and has no opinion on share codes. `ResumeController`'s two download actions now write to **both**: `ResumeDownload::record()` (unchanged, still the share-code audit trail) and `DocumentDownload` (new, the cache/format record this change needs). Merging them would mean giving `ResumeDownload` `type`/`served_cached_document` columns it has no other use for, and giving `document_downloads` a `share_code_id` that means nothing for a targeted resume or cover letter — keeping them separate keeps each table's columns meaningful for every row in it.

### 2. Existence + invalidation move onto the models, via one shared trait

Add `App\Models\Concerns\HasGeneratedDocuments`, applied to `ResumeVersion`, `TargetedResume`, and `CoverLetter` (all three already have `docx_path`/`pdf_path` columns):

```php
trait HasGeneratedDocuments
{
    public function docxExists(): bool
    {
        return $this->docx_path !== null && file_exists($this->docx_path);
    }

    public function pdfExists(): bool
    {
        return $this->pdf_path !== null && file_exists($this->pdf_path);
    }

    public function invalidateDocx(): void
    {
        if ($this->docx_path !== null && file_exists($this->docx_path)) {
            @unlink($this->docx_path);
        }
        $this->forceFill(['docx_path' => null])->save();
    }

    public function invalidatePdf(): void
    {
        if ($this->pdf_path !== null && file_exists($this->pdf_path)) {
            @unlink($this->pdf_path);
        }
        $this->forceFill(['pdf_path' => null])->save();
    }

    public function invalidateDocuments(): void
    {
        $this->invalidateDocx();
        $this->invalidatePdf();
    }
}
```

`TargetedResume`/`CoverLetter`'s existing local `docxExists()`/`pdfExists()` methods are removed in favor of the trait (identical logic, now shared). `ResumeVersion` gains exists/invalidate methods it never had; `GeneratesResumeDocuments::docxExistsForCurrentVersion()`/`getLatestDocxPath()`/`getLatestPdfPath()` (service-layer, resolve the *current* version first) are unchanged and continue to be how the main-resume download routes find *which* `ResumeVersion` to check — the trait then answers the resulting model's own exists/invalidate questions. `getLatestPdfPath()` already existed (unlike `pdfExistsForCurrentVersion()`, which proposal.md adds) — both are now used by `ResumeController::downloadPdf()`'s generate-if-missing check.

**Alternative considered**: a standalone `DocumentInvalidator` service taking `(Model $document, string $docxPathColumn, string $pdfPathColumn)`. Rejected — the column names are identical (`docx_path`/`pdf_path`) on all three models, so a trait needs no parameterization and reads naturally as `$targetedResume->invalidateDocuments()` at each call site instead of `$invalidator->invalidate($targetedResume)`.

### 3. Generate-if-missing lives in each document service, not the controllers

Add `ensureDocx(): array{success: bool, path?: string, error?: string, served_cached_document: bool}` and `ensurePdf(): array{...}` to each of the three places that currently expose `generateDocx()`/`generatePdf()` — `GeneratesResumeDocuments` (for `ResumeVersion`, via `DatabaseResumeVersionService`), `TargetedResumeDocumentService`, `CoverLetterDocumentService`:

```php
public function ensureDocx(TargetedResume $targetedResume): array
{
    if ($this->isCurrentlyValid($targetedResume->docx_path)) {
        return ['success' => true, 'path' => $targetedResume->docx_path, 'served_cached_document' => true];
    }

    $result = $this->generateDocx($targetedResume);

    return $result + ['served_cached_document' => false];
}
```

`isCurrentlyValid(?string $path)` centralizes the two retention modes (Decision 4): in `delete_after_serve` mode it's just `$path !== null && file_exists($path)` (a file that exists has not been served yet, by construction — see Decision 4); in `cache` mode it additionally checks `filemtime($path)` against `now()->subHours(config('resume.document_retention_hours'))`.

Each of the three services already differs in how it locates the model (`ResumeVersionServiceContract` resolves the current version internally; `TargetedResumeDocumentService`/`CoverLetterDocumentService` take the model as a parameter), so `ensureDocx()`/`ensurePdf()` are added with each service's own existing parameter shape — there is no single shared interface across all three, matching how `generateDocx()`/`generatePdf()` already aren't a shared interface today.

The five download controller actions become: call `ensureDocx()`/`ensurePdf()`, return the download-failure response from `on-demand-document-generation`'s "A download-time rendering failure is reported" requirement if it failed, otherwise log via `DocumentDownloadLogger` (Decision 5) using the returned `served_cached_document` flag, then serve the file (Decision 4 governs whether `deleteFileAfterSend` is set).

**Alternative considered**: check-then-generate as two separate calls in each controller action (`if (! $service->docxExists()) { $service->generateDocx(); }`). Rejected — `served_cached_document` needs to be known accurately for logging, and computing it correctly (including the cache-mode TTL check) belongs with the generation logic, not duplicated five times across three different controllers.

### 4. Retention mode governs `isCurrentlyValid()` and how the response is built

`config('resume.document_retention_mode')` is `cache` (default) or `delete_after_serve`; `config('resume.document_retention_hours')` (default `12`) only matters in `cache` mode.

- **`cache` mode**: `isCurrentlyValid()` checks the file's mtime against the retention window (Decision 3). A separate scheduled command, `resume:sweep-expired-documents`, runs hourly (`routes/console.php`, alongside the existing `Schedule::command(...)` entries) and calls `invalidateDocx()`/`invalidatePdf()` (Decision 2) on every `ResumeVersion`/`TargetedResume`/`CoverLetter` row whose respective file's mtime is past the window — so a file that nobody downloads again still gets reclaimed eventually, not just lazily invalidated on the next request that happens to check it.
- **`delete_after_serve` mode**: the download response is built with Symfony's built-in `BinaryFileResponse::deleteFileAfterSend(true)` (`response()->download($path, ...)->deleteFileAfterSend(true)`), which unlinks the file on disk once the response has finished streaming — no custom terminate-callback or job needed. The model's `docx_path`/`pdf_path` column is set to `null` (via `forceFill(...)->save()`, **not** `invalidateDocx()`/`invalidatePdf()`, which would `unlink()` the file before it has been served) at the same point the response is built, so the app stops considering the file current immediately, while the bytes on disk survive long enough for Symfony to actually stream them. The sweep command still runs in this mode too (harmless — there should be nothing for it to find, since nothing lingers past being served) rather than being conditionally registered, keeping `routes/console.php` unconditional.

**Alternative considered**: a `DeleteGeneratedDocumentAfterServe` middleware/terminable listener instead of `deleteFileAfterSend()`. Rejected — Symfony's `BinaryFileResponse` already does exactly this, and reimplementing it manually would be strictly more code for the same behavior.

### 5. Download logging: one small service, called from all five actions

`App\Services\DocumentDownloadLogger`:

```php
class DocumentDownloadLogger
{
    public function log(ResumeVersion|TargetedResume|CoverLetter $document, string $type, bool $servedCachedDocument, string $ipAddress): DocumentDownload
    {
        return DocumentDownload::create([
            'resume_id' => $document instanceof ResumeVersion ? $document->id : null,
            'targeted_resume_id' => $document instanceof TargetedResume ? $document->id : null,
            'cover_letter_id' => $document instanceof CoverLetter ? $document->id : null,
            'type' => $type,
            'served_cached_document' => $servedCachedDocument,
            'ip_address' => $ipAddress,
        ]);
    }
}
```

Called from each controller action with `$request->ip()`. Kept as a thin injectable service (not a static call on the model, unlike `ResumeDownload::record()`) so it's trivial to assert against in the five controller tests without touching the database directly.

### 6. `DocumentDownload` gains `ip_address` and one `Attribute`-based accessor

New migration adds `ip_address` (`string(45)`, nullable — matching `ResumeDownload.ip_address`'s width for IPv6) to `document_downloads`, added to `$fillable`.

Per Laravel 13 convention, `DocumentDownload` gains a computed accessor using `Illuminate\Database\Eloquent\Casts\Attribute` (the modern combined accessor/mutator API) rather than a legacy `getXAttribute()` method, for the one place real accessor logic exists — resolving whichever of the three nullable relations is actually set on a given row into a single value:

```php
protected function document(): Attribute
{
    return Attribute::make(
        get: fn (): ResumeVersion|TargetedResume|CoverLetter|null => $this->resume ?? $this->targetedResume ?? $this->coverLetter,
    );
}
```

`served_cached_document`'s boolean cast stays a plain `casts()` entry (`app/Models/DocumentDownload.php`, unchanged from how it was added) — it is a direct type cast with no computation, and this project's own Laravel-13 convention (`config/resume.php`'s neighboring models) already prefers `casts()` for that case; `Attribute::make()` is reserved for cases with actual accessor/mutator logic, which a straight bool cast is not.

### 7. Write paths call `invalidateDocuments()` instead of `generateDocx()`/`generatePdf()`

`ResumeEditCandidateService::approve()`, `Admin\ResumeEditorController::saveResumeData()`, `TargetedResumeService::saveTailoredResume()`/`updateTailoredMarkdown()`, and `Admin\CoverLetterController::generateDocuments()` (renamed to reflect its new behavior, or removed with its two call sites calling `invalidateDocuments()` directly — a one-line change either way) each replace their `generateDocx()`+`generatePdf()` calls with a single `invalidateDocuments()` call on the relevant model. `approve()`'s return type stays `array{success: bool, error?: string}` for now (unchanged shape), but `error` is now unreachable — nothing after the `DB::transaction()` commit can fail. `updateTailoredMarkdown()`'s `throw`-on-failure behavior in `saveTailoredResume()` (chat finalize path) and its failure-array return in the manual-edit path both go away for the same reason.

**Alternative considered**: leave `approve()`'s dead `error` branch in place "just in case." Rejected — an unreachable branch that used to matter is worse than no branch; a future reader would waste time figuring out how it could still fire.

## Risks / Trade-offs

- **[Risk] The first download after a save is always a cache miss and pays the full render cost inline** → Accepted. Per `replace-libreoffice-with-weasyprint`'s own measurements, a render is ~0.7s (PDF) or faster (DOCX, pure PHP) — well within what a download request can absorb, and it is the download requester who is already waiting on a file, not an unrelated approval/save response.
- **[Risk] `delete_after_serve` mode means every download of the same document renders fresh, even seconds apart** → Accepted as the explicit trade-off of that mode (privacy/storage over avoided re-renders); documented in proposal.md and the spec. An operator who wants repeat-download efficiency picks `cache` mode instead.
- **[Risk] The hourly sweep command failing silently would let `cache`-mode files accumulate indefinitely** → Logged like any other scheduled command failure (Laravel's scheduler already reports failures); no new alerting added in this change, consistent with `document_generation_status`-style read of accepted risk elsewhere in this codebase (queryable via tinker, not proactively alerted).
- **[Risk] `deleteFileAfterSend()` races with a second concurrent download request for the same file** → A second request that reads `docx_path`/`pdf_path` before it's nulled, but after Symfony has started unlinking the first response's file, could 404 or serve a partial read. Accepted as an edge case: this is a personal single-operator site's admin/share-code-gated download surface, not a high-concurrency public endpoint, and the failure mode (retry the download, it regenerates) is the same "acceptable, cheap to recover from" trade-off already accepted for a failed on-demand render.
- **[Trade-off] Retention mode is a single global setting, not configurable per document type** → Deliberate (proposal.md, "applied uniformly"): the three document types don't have different privacy/storage profiles that would justify per-type configuration, and a single setting is one less thing to reason about when tuning it.

## Migration Plan

1. Add and run the `document_downloads` `ip_address` migration against both the app database and the `wink` test database, per this repo's dual-database convention.
2. Deploy the code: `HasGeneratedDocuments` trait, `ensureDocx()`/`ensurePdf()` on all three services, `DocumentDownloadLogger`, the five updated controller actions, the four updated write paths, the new config keys (with their defaults — `cache` / `12` — so an un-set `.env` behaves exactly like today's "keep the file" expectation), and the new scheduled command.
3. No backfill: existing `docx_path`/`pdf_path` values on already-generated documents remain valid and servable as-is (they satisfy `isCurrentlyValid()` in `delete_after_serve` mode immediately, and in `cache` mode until their file's existing mtime ages out) — nothing needs to be pre-invalidated for this change to take effect; the next save/approve of each document is what starts it participating in invalidate-on-write.
4. No queue-worker restart is required by this change specifically (unlike the now-superseded `offload-resume-docx-generation`) — nothing here is queued; the scheduled command runs under the existing Laravel scheduler (`schedule:run`), not `queue:work`.

Rollback: revert the code deploy. The `ip_address` column is additive and harmless to leave in place. Any file a `cache`-mode sweep already deleted is regenerated on its next request either way, whether running old or new code.
