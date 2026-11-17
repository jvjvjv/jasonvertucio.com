## Why

"Ghosted" is only a label today. An `applied` application that has been silent past the threshold is drawn as ghosted in the list and on the Discussion page, but its stored status stays `applied`. So I can't filter for it, the history never says when the silence began, and silence after an interview isn't caught at all. Nothing remembers which companies went silent on me either, so the next posting from a company that ghosted me scores as well as any other.

## What Changes

- **BREAKING (data meaning): `ghosted` becomes a stored status.** It is a new `ApplicationStatus` case. It is a pipeline status but not a terminal one, because a company can still reply after going quiet.
- **Applications are marked ghosted automatically.** A daily scheduled run finds non-deleted applications in `applied` or `interviewed` whose latest status-history entry is older than the threshold, and adds a `ghosted` history entry to each one.
  - The threshold is the existing `config('resume.ghosted_after_days')` (`RESUME_GHOSTED_AFTER_DAYS`, default 30).
  - The entry is dated the day the threshold was crossed: the latest entry's date plus the threshold. Its notes say it was recorded automatically.
  - Applications in `interviewing`, `offered` or a terminal status are never marked.
  - The run can also be started by hand with a `--dry-run` preview.
- **Ghosted can be recorded by hand**, from `applied` or `interviewed`, through the status-history form or the session's `update-status` tool.
- **A ghosted application can move on.** If the company replies, the next status is offered from `ghosted`: `interviewing`, `offered`, `rejected` or `withdrawn`. Logging it moves the application out of ghosted.
- **Removed: the computed ghosted display status.** The PHP `ApplicationStatusResolver`, the TypeScript `resolveApplicationDisplayStatus`, the `useGhostedAfterDays` hook and the `ghostedAfterDays` prop on the list and Discussion pages all go. Every page shows the stored status. Filtering by `applied` no longer includes silent applications; `ghosted` is its own filter option.
- **Companies are tallied by how many of my applications they are currently ghosting.**
  - The tally counts non-deleted applications whose status is `ghosted`.
  - Applications belong to the same company when they share a researched Company (through Role intel) or have the same company name, ignoring case and surrounding spaces.
  - When an application moves out of `ghosted`, it drops off the tally.
- **Companies on the tally rank lower.**
  - Every other application at that company shows an adjusted fit score: the AI's score minus `config('resume.ghosted_fit_penalty')` (`RESUME_GHOSTED_FIT_PENALTY`, default 10) for each ghosted application, never below 1.
  - It also shows a "Ghosted you N×" marker.
  - The stored `fit_score` is unchanged. Both the Applications list and the Discussion page show the adjusted score, and the raw score is still visible.
- **The application's AI session is told about the tally.** `get-targeted-resume-context` gains an additive `company_ghosting` key. It tells the model to score on merit, because the penalty is applied separately.
- **Metrics read the stored status.**
  - The Ghosted outcome is "latest status is `ghosted`", unless an accepted, hired, rejected or withdrawn entry outranks it.
  - A `ghosted` entry is not a response from the employer.
  - On the Timeline, the ghosted segment now starts at the `ghosted` entry instead of relabelling the trailing `applied` segment.
  - The ghosted-rate applicability rule is unchanged.

## Capabilities

### New Capabilities

- `application-ghosting`: automatic marking of silent applications as ghosted, the per-company ghosting tally, the fit-score penalty and marker, and telling the AI session about it.

### Modified Capabilities

- `application-tracking`: "Application statuses" gains `ghosted` as a stored, non-terminal status, offered in the status filter, and drops the display-only rule. "Withdrawing an application" offers `withdrawn` from `ghosted` too and reworks its "never ghosted" scenario.
- `application-metrics`: a new requirement defines the Ghosted outcome, response handling and the Timeline segment from the stored status. "Ghosted rate availability" and "Withdrawn applications in metrics" are unchanged.

## Impact

- **Backend:**
  - `App\Enums\ApplicationStatus` gains `Ghosted`, along with `allowedNext()` and the `isTerminal()` and `isPipeline()` classifications;
  - `ApplicationService` gains `markGhosted()`;
  - a new `applications:mark-ghosted` command, scheduled daily in `routes/console.php`;
  - a new ghosting-tally service;
  - `ApplicationController` list rows and `ApplicationPayload` gain `ghosting` (`count`, `penalty`, `adjusted_fit_score`);
  - `ApplicationMetricsService` reads the stored status;
  - `GetTargetedResumeContextTool` and the `UpdateStatusTool` description change;
  - `App\Support\ApplicationStatusResolver` is deleted;
  - `config/resume.php` updates the ghosted comment and gains `ghosted_fit_penalty`.
- **Frontend:**
  - `ApplicationRow.tsx` and `StatusBar.tsx` show the stored status and the adjusted fit score with its marker;
  - `ChatPanel.tsx`'s header fit text changes;
  - `utils/applicationStatus.ts` loses the resolver;
  - `useGhostedAfterDays.ts` is deleted;
  - the list and Discussion pages stop receiving `ghostedAfterDays`. The Metrics page keeps it for the card label and applicability.
- **Database:** no schema change, because `status` columns are strings. Existing silent applications are converted by the first run of the command, not by a migration.
- **Other change in flight:** `add-application-contact-follow-ups` is not implemented yet, and it adds an exhaustive `ApplicationStatus::receivesFollowUps()` match. Whichever change lands second must classify `Ghosted` there (as `false`).
- **Operations** (developer-owned):
  - production's scheduler must be running;
  - after deploy, run `php artisan applications:mark-ghosted --dry-run`, then run it for real once, or wait for the daily run;
  - restart the `queue:work` worker as usual.
- **Docs:** `.claude/context/applications.md` ("Ghosted" bullet) and `CLAUDE.md`, if anything there mentions the display status.
