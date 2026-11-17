All PHP tests use `DatabaseTransactions` (never `RefreshDatabase`) and run against the `wink` test database. No migration is needed.

## 1. Status enum and transitions

- [x] 1.1 Add `ApplicationStatus::Ghosted` (`'ghosted'`) between `Offered` and `Accepted`, so that it is a pipeline status and not terminal. Extend `allowedNext()`:
  - `Applied` gains `Ghosted`;
  - `Interviewed` gains `Ghosted`;
  - new arm `Ghosted => [Interviewing, Offered, Rejected, Withdrawn]`.

  Update `tests/Unit/Enums/ApplicationStatusTest.php` to cover pipeline, non-terminal and each transition, and verify that test passes.
- [x] 1.2 Update `UpdateStatusTool`'s description: add a "the company ghosted me" → `ghosted` example and list `ghosted` among the valid statuses. Add a test that the tool accepts `ghosted` and records a matching entry, and verify it passes.
- [x] 1.3 Add `ghosted_fit_penalty` (`RESUME_GHOSTED_FIT_PENALTY`, default 10) to `config/resume.php`, and rewrite the `ghosted_after_days` comment: it is now the automatic-marking threshold, and the status is stored. Verify with `php artisan config:show resume`.

## 2. Automatic marking

- [x] 2.1 Add `ApplicationService::markGhosted(Application, int $days): ?ApplicationStatusUpdate`. It locks the row in a transaction, re-checks status ∈ {applied, interviewed} and latest `occurred_at < now − days`, then calls `addStatusUpdate()` with `Ghosted`, the date `latest + days` and the automatic note. Otherwise it returns null. Add `Services/ApplicationServiceTest` cases and verify they pass:
  - applied marked;
  - interviewed marked;
  - recent not marked;
  - already ghosted or other statuses return null;
  - the entry date and note are correct;
  - a second call adds nothing.
- [x] 2.2 Create `applications:mark-ghosted {--dry-run}` with `php artisan make:command`. It selects candidates in SQL (non-deleted, applied/interviewed, latest entry older than the threshold), chunked, and calls `markGhosted()`. `--dry-run` prints id, company, position, status and ghosted-on date, and writes nothing. Add a feature test and verify it passes. The test covers:
  - the configured threshold (14 days);
  - soft-deleted applications skipped;
  - interviewing, offered and terminal statuses skipped;
  - the dry run writes nothing;
  - running twice gives one entry;
  - an application updated between selection and marking is skipped.
- [x] 2.3 Schedule it in `routes/console.php` (`dailyAt('03:00')->withoutOverlapping()`), and verify `php artisan schedule:list` shows it.

## 3. Remove the computed display status

- [x] 3.1 Delete `App\Support\ApplicationStatusResolver`. Remove `ghostedAfterDays` from `ApplicationController::index` and `show`. Verify `grep -rn ApplicationStatusResolver app tests` returns nothing.
- [x] 3.2 Delete `resolveApplicationDisplayStatus` from `resources/js/admin/utils/applicationStatus.ts` (keep `isPipelineStatus`) and delete `useGhostedAfterDays.ts`. Make `ApplicationRow.tsx`, `StatusBar.tsx` and `Index.tsx` pass the stored status to `StatusChip` and drop the `ghostedAfterDays` prop. Verify that `npx tsc --noEmit` and `npm run build` succeed.
- [x] 3.3 Rewrite the existing tests that relied on the computed display so they add a `ghosted` entry: `ApplicationCoreSmokeTest`, `ApplicationHttpSmokeTest`, `ApplicationFormRequestTest`, and any list or Discussion assertions on `ghostedAfterDays`. Add a list test: filtering `status[]=ghosted` lists only ghosted applications, and `status[]=applied` excludes them. Verify those files pass.

## 4. Company ghosting tally and fit penalty

- [x] 4.1 Create `App\Services\CompanyGhostingTally` (`php artisan make:class`) with `forApplications(Collection): array<int,int>`, `forApplication(Application): int`, `penaltyFor(int): int` and `adjustedFitScore(?int, int): ?int`. Matching keys:
  - the Role-intel company id;
  - the lowercased trimmed `company_name`, skipped for `UNKNOWN_COMPANY`;
  - the lowercased Company name.

  It counts distinct non-deleted ghosted applications other than the target itself. Add a feature test for every scenario in the spec's "Company ghosting tally" requirement, plus the floor of 1 and the configured penalty, and verify it passes.
- [x] 4.2 Add `ghosting: {count, penalty, adjusted_fit_score}` to `ApplicationController::listRow()` (one `forApplications()` call per list) and to the Discussion page's application payload. Extend `ApplicationListTest` and `ApplicationDiscussionTest` and verify they pass. The tests assert:
  - the values for a penalised application;
  - a zero tally for an application at a company with no ghosting;
  - that the tally drops after the ghosted application gets a `rejected` entry.
- [x] 4.3 Add the `ghosting` type to `resources/js/types/index.ts`. Then update:
  - **`ApplicationRow` fit cell:** when `count > 0`, the adjusted score, the stored score muted, a "Ghosted you N×" chip and a tooltip;
  - **`StatusBar`:** "Fit: A% (S − P)" and the chip;
  - **`ChatPanel`'s header:** the adjusted score.

  Follow `.claude/context/admin-design.md` (tokens, no gradients or shadows). Verify that `npm run build` succeeds, then load the list and a Discussion page locally with a ghosted company and capture screenshots for the developer.
- [x] 4.4 Add `company_ghosting {ghosted_applications, penalty_per_ghosting, note}` to `GetTargetedResumeContextTool`'s response. The note says the penalty is applied separately and the session should score on merit. Add a test for tally 1 and tally 0 and verify it passes.

## 5. Metrics

- [x] 5.1 Update `ApplicationMetricsService`:
  - `outcome()` is `ghosted` when the latest entry is `Ghosted`, after the accepted/hired, rejected and withdrawn checks;
  - drop `$ghostedAfterDays` from `outcome()`, `kpis()` and `timeline()`;
  - first-response detection skips `ghosted`;
  - remove the timeline's trailing-segment relabel.

  Keep `ghostedAfterDays` and `ghostRateApplicable` in the payload.
- [x] 5.2 Update `ApplicationMetricsTest`, `ApplicationMetricsHttpTest` and `ResumeMetricsTest` to build stored `ghosted` entries, and add a test for each scenario in the "Ghosted applications in metrics" requirement:
  - ghosted after an interview;
  - not a response;
  - replied after ghosting (rejected, interviewing);
  - silent but unmarked is in progress;
  - the timeline segments.

  Verify those files pass.

## 6. Docs, coordination and review

- [x] 6.1 Update `.claude/context/applications.md`:
  - rewrite the "Ghosted is a display status" bullet: stored, the daily command, the backdated entry, deleting is re-marked, the transitions;
  - add the company tally and penalty, and the `ghosting` payload;
  - list the new command and service under Key Files.

  Remove any display-status mention in `CLAUDE.md`. Verify with `grep -rn "display status" .claude CLAUDE.md`.
- [x] 6.2 Add a note to `openspec/changes/add-application-contact-follow-ups/tasks.md` (the task that adds `receivesFollowUps()`): classify `Ghosted => false` if this change has merged first. If that change has already merged, add the arm here instead. Verify the enum has no `match` without a `Ghosted` arm (`grep -n "match (\$this)" app/Enums/ApplicationStatus.php`, then read each one).
- [x] 6.3 Fix Prettier and ESLint issues in the files this change touched, so the husky hook passes, and verify by running the project's lint on them.
- [x] 6.4 Run the `laravel-reviewer` agent over the diff and address its findings. Then run `php artisan test --compact --filter='Application|Metrics|Ghost'` and verify it is green. Ask the developer whether to run the full suite.

## 7. Deploy (developer-owned, do not run)

- [ ] 7.1 Developer: deploy, then run `php artisan applications:mark-ghosted --dry-run` in production, review it, run `php artisan applications:mark-ghosted`, confirm `php artisan schedule:list` lists the new command, and restart the `queue:work` worker via `supervisorctl`.
