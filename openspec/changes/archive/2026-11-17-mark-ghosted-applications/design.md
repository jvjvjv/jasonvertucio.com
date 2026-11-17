## Context

- **Ghosted is computed today.** `App\Support\ApplicationStatusResolver::resolve()` and `resolveApplicationDisplayStatus()` in `resources/js/admin/utils/applicationStatus.ts` turn an `applied` application into "ghosted" when `latestStatusUpdate.occurred_at` is older than `config('resume.ghosted_after_days')`. The two must agree, which is why the list and Discussion pages receive `ghostedAfterDays` (read by `useGhostedAfterDays`). `ApplicationMetricsService::outcome()` and `timeline()` call the PHP resolver.
- **Statuses are strings.** `applications.status` and `application_status_updates.status` are `string(50)` columns cast to `ApplicationStatus`, so a new case needs no migration. `ApplicationStatus::pipeline()` feeds both the status-history form and the `update-status` tool's schema `enum`. `ApplicationPayload::allowedNextStatuses()` sends `allowedNext()` to the client. `ApplicationController::index` builds the status filter from `cases()`.
- **History drives status.** `ApplicationService::addStatusUpdate()` writes the entry and sets `applications.status` in one transaction. `latestStatusUpdate` is `latestOfMany('occurred_at')`. `occurred_at` is a timestamp.
- **There is no organization key on an application.** `applications.company_name` is free text, defaulting to `Application::UNKNOWN_COMPANY`. A researched `Company` reaches an application only through `RoleIntel.company_id`. `ApplicationController::companyIntelByName()` already matches applications to companies by lowercased name.
- **Fit score** is written only by the AI session's tools (`UpdateFitAssessmentTool`, `SaveTailoredResumeTool`) and by the Details form. Nothing sorts by it.
- The scheduler already runs commands from `routes/console.php`.
- The in-flight `add-application-contact-follow-ups` change (0/21 tasks) adds an exhaustive `ApplicationStatus::receivesFollowUps()`.

## Goals / Non-Goals

**Goals:**
- One authoritative ghosted state, stored and dated in the history, with no client/server mirror to keep in sync.
- A tally that can't drift. It is derived from current statuses at read time, never kept as a counter.
- A penalty that is visible and reversible: the stored score is untouched and the raw score stays on screen.

**Non-Goals:**
- No `organizations` table, and no merging of differently named companies beyond the Role-intel link.
- No change to list ordering (it stays most recently active first). Nothing new sorts by fit score.
- No ghosting marker on the New Session form, the Company Intel pages or the public MCP server.
- No marking of `interviewing` (it is active, and its date is often a future interview) or of `offered`.
- No admin settings UI. The threshold and penalty stay in `config/resume.php` and env.

## Decisions

### 1. `Ghosted` is an enum case, non-terminal, with explicit transitions

Add `case Ghosted = 'ghosted'` between `Offered` and `Accepted`. `isPipeline()` is true through its `default` arm, and `isTerminal()` is false, so a late reply can still be logged.

`allowedNext()` changes as follows:
- `Applied` gains `Ghosted`;
- `Interviewed` gains `Ghosted`;
- new: `Ghosted => [Interviewing, Offered, Rejected, Withdrawn]`.

`Interviewed` from `Ghosted` is left out because the interview has to happen first. `allowedNext()` stays advisory.

The `update-status` tool needs no schema change, because its enum comes from `pipeline()`. Its description gains a "they ghosted me" → `ghosted` example and lists `ghosted` among the valid statuses.

*Alternative:* a terminal `ghosted`. Rejected, because the user wants a late reply to move the application on and to take it off the company's tally.

### 2. Marking goes through `ApplicationService::markGhosted()`, called by a daily command

`applications:mark-ghosted {--dry-run}` (`app/Console/Commands/MarkGhostedApplicationsCommand.php`) is scheduled `->dailyAt('03:00')->withoutOverlapping()`.

**Selection** is in SQL: non-deleted applications with `status IN (applied, interviewed)` and a latest status entry older than `now() - threshold`. It uses a `whereHas('latestStatusUpdate', …)` or a correlated `MAX(occurred_at)` subquery, and is chunked by id.

**`markGhosted(Application $application, int $days): ?ApplicationStatusUpdate`**:
1. Opens a transaction and locks the row (`lockForUpdate`).
2. Reloads the status and the latest entry, then re-checks both conditions.
3. If they still hold, calls `addStatusUpdate($application, Ghosted, $notes, $latest->occurred_at->copy()->addDays($days))`. The notes read "Marked ghosted automatically after {days} days with no update."
4. Otherwise returns null.

Re-checking under the lock is what makes "updated before being marked" and "running twice" safe. `addStatusUpdate`'s nested transaction becomes a savepoint.

`--dry-run` prints a table (id, company, position, current status, ghosted-on date) and writes nothing.

The comparison is `occurred_at < now()->subDays($days)`, the same strictness as today's resolver, so the switch neither marks nor spares anything at the boundary that the display rule treated differently.

**Why the backdated date:** the history should say when the silence passed the threshold. That also makes the first run, which catches up a backlog, produce accurate Timeline segments.

*Alternative:* write a stored status without a history entry. Rejected, because the system's invariant is "status = latest entry". Deleting the entry is also the natural undo.

*Alternative:* do the backfill in a migration. Rejected, because the command is idempotent, testable and previewable. A migration would duplicate its logic.

### 3. The ghosting tally is computed per request by one service

The service is `App\Services\CompanyGhostingTally`, with these methods:

- **`forApplications(Collection $applications): array<int, int>`**: maps application id to tally, from one query over the whole list.
- **`forApplication(Application): int`**: the same calculation for a single application.
- **`penaltyFor(int $tally): int`** and **`adjustedFitScore(?int $fit, int $tally): ?int`**: the penalty is `config('resume.ghosted_fit_penalty') × tally`, and the adjusted score is `max(1, fit − penalty)`, or null when there is no fit score.

How the tally is built:
1. Load every non-deleted `ghosted` application with `roleIntel.company:id,name`. This set is small, at most a few hundred rows.
2. For each one, build its keys: `company:{id}` when its Role intel has a company, `name:{lowercased trimmed company_name}` unless it is `UNKNOWN_COMPANY`, and `name:{lowercased Company.name}`.
3. The target application builds the same keys. Its tally is the number of distinct ghosted applications, other than itself, that share any key with it.

**Why derived, not stored:** "drops off when the company replies" and "deleted doesn't count" then hold with no event hooks. A counter column would need decrementing on every status change, every deletion and every restore.

*Alternative:* a `ghosted_count` on `companies`. Rejected, because most applications have no `Company` row, since intel is optional.

### 4. One `ghosting` object in the payloads

`listRow()` and the Discussion page's application payload (`ApplicationController::show` / `ApplicationPayload`) each gain:

```
ghosting: { count: int, penalty: int, adjusted_fit_score: ?int }
```

`count` is the tally. `penalty` is the total deduction. The client does no arithmetic, so the threshold and penalty values never need to reach it.

The UI follows `.claude/context/admin-design.md` (no gradients, no shadows):
- **`ApplicationRow` fit cell:** when `count > 0`, it shows the adjusted score with the stored score in muted text ("58% · 78") and a small warning-coloured "Ghosted you 2×" chip under it. A tooltip explains the penalty.
- **`StatusBar`:** shows "Fit: 58% (78 − 20)" and the same chip.
- **`ChatPanel`'s header text:** uses the adjusted score.

When `count === 0` the rendering is unchanged.

### 5. AI session context

`GetTargetedResumeContextTool` and `GetJobDescriptionTool` (the one a session reads while it scores fit, before any targeted resume exists) add the following, using `forApplication()`:

```
company_ghosting: {
  ghosted_applications: N,
  penalty_per_ghosting: P,
  note: "The system lowers this company's displayed fit score by P per ghosted application. Score the role on its merits; do not apply this yourself."
}
```

The key is additive, so it doesn't break the tool's existing response-key contract. Without the note, the penalty would likely be applied twice.

### 6. Removing the display resolver

The following are deleted:
- `ApplicationStatusResolver`;
- `resolveApplicationDisplayStatus` (`isPipelineStatus` stays in `applicationStatus.ts`);
- `useGhostedAfterDays.ts`;
- the `ghostedAfterDays` prop from `ApplicationController::index` and `show` and from `Index.tsx` and `ApplicationRow.tsx`.

`ApplicationRow` and `StatusBar` pass the stored `status` to `StatusChip`. `statusColor.ts` and `statusChartColor.ts` already have a `ghosted` case.

### 7. Metrics

- **`outcome()`:** after the accepted/hired, rejected and withdrawn checks, the outcome is `ghosted` when the latest entry's status is `Ghosted`, and `in_progress` otherwise. The `$ghostedAfterDays` parameter goes away from `outcome()`, `kpis()` and `timeline()`.
- **Responses:** first-response detection skips `applied`, `withdrawn` and `ghosted`. `STAGE_RANKS` has no `ghosted` key, so it already ranks 0 and is not a response.
- **`timeline()`:** the special case that relabels the trailing segment goes away. The `ghosted` entry is a normal non-terminal last segment that runs to now.
- **Unchanged:** `ghostedAfterDays` and `ghostRateApplicable` stay in the payload (the card label and the applicability rule).

### 8. Coordination with `add-application-contact-follow-ups`

That change's `receivesFollowUps()` is an exhaustive `match`. Whichever change merges second adds `self::Ghosted => false`. A ghosted company has already ignored the follow-ups sent at days 7, 14 and 21. Its `addStatusUpdate()` discard hook also covers the automatic `ghosted` entry, so a pending draft is discarded when an application is marked. This is noted in both changes' tasks.

## Risks / Trade-offs

- **[A silent application looks `applied` for up to a day]** Between the threshold passing and the next daily run, the list shows `applied`. That is acceptable at a one-day granularity. The command can be run by hand.
- **[First run marks a backlog at once]** After deploy, every long-silent `applied` and `interviewed` application gets marked in one go. → `--dry-run` is run first (developer-owned), and the entries are backdated, so the history reads correctly.
- **[Name matching is loose]** "Acme" and "Acme Inc" don't match unless one has Role intel pointing at a Company named like the other. Similarly named but different companies would be falsely penalised. → The marker is visible, the penalty is display-only, and an exact-name match is the rule the list's Intel column already uses.
- **[Deleting an automatic entry is undone by the next run]** That is intended: the data still says the application is silent. The spec says to log the real status instead. This is documented in `applications.md`.
- **[Backdated reply between the latest entry and the ghosted date]** If the admin later logs a reply dated before the `ghosted` entry, `addStatusUpdate` still sets the status to the new entry, but `latestStatusUpdate` (by `occurred_at`) stays `ghosted`. → This existing ordering quirk applies to any backdated entry. The status-history form defaults to today, and `applications.md` notes it. No new handling.
- **[Existing tests assume the computed display]** Tests that mention ghosting (`ApplicationMetricsTest`, `ApplicationMetricsHttpTest`, `ResumeMetricsTest`, `ApplicationCoreSmokeTest`, `ApplicationHttpSmokeTest`, `ApplicationFormRequestTest`, `Services/ApplicationServiceTest`, `Unit/Enums/ApplicationStatusTest`) may build an old `applied` entry and expect "ghosted". → Rewrite them to add a `ghosted` entry. The "Application on the period's first day already past the threshold" metrics scenario still holds once the entry exists.
- **[Tally query on every list load]** One extra query over the ghosted rows, then an in-memory key match. That is negligible at personal-site scale.

## Migration Plan

1. Deploy the code. No migration is needed.
2. (Developer) `php artisan applications:mark-ghosted --dry-run`, review the list, then `php artisan applications:mark-ghosted`.
3. (Developer) Confirm the scheduler runs in production (`php artisan schedule:list` shows the new entry), and restart the `queue:work` worker via `supervisorctl` as on every deploy.

**Rollback:** revert the code, then delete the automatically created `ghosted` entries and recompute statuses. Deleting each entry through `ApplicationService::deleteStatusUpdate()` does both, and the notes text identifies the automatic entries. A reverted enum without `ghosted` would fail to cast those rows, so the entries must go before or with the revert.
