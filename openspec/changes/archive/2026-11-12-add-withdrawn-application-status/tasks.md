## 1. Status enum

- [x] 1.1 Add `case Withdrawn = 'withdrawn';` to `app/Enums/ApplicationStatus.php`, include it in `isTerminal()`, add it to `allowedNext()` for `Applied`, `Interviewing`, `Interviewed` and `Offered` (it falls through to `[]` itself), and update the docblock of `app/Exceptions/TerminalApplicationException.php` to name it. Update `tests/Unit/Enums/ApplicationStatusTest.php` (case list, pipeline and terminal providers, `allowedNext` expectations) and verify with `php artisan test --compact tests/Unit/Enums/ApplicationStatusTest.php`.

## 2. Status history and agent tool

- [x] 2.1 In `tests/Feature/ApplicationStatusUpdateTest.php`, add tests for: a `withdrawn` entry on an `applied` application stores its notes/date and sets the status to `withdrawn`; adding any entry to a `withdrawn` application is refused (422, as for every terminal status) and stores nothing; deleting the `withdrawn` entry restores the previous status (e.g. `interviewing`). Verify they pass with `--filter`.
- [x] 2.2 In `tests/Feature/ApplicationDiscussionTest.php` (or wherever `allowed_next_statuses` is already asserted), assert `withdrawn` is in `allowed_next_statuses` for `interviewed` and absent for `draft` and `passed`, and that a `withdrawn` application's list is empty. Verify with `--filter`.
- [x] 2.3 Add a test that an application whose stored status is `withdrawn` and whose latest entry is older than `resume.ghosted_after_days` resolves to `withdrawn` through `ApplicationStatusResolver::resolve()` (not `ghosted`). No code change expected. Verify the test passes.
- [x] 2.4 Update the `#[Description]` of `app/Services/Mcp/Tools/TargetedResume/UpdateStatusTool.php` and the `update-status` line in `TargetedResumeService`'s system prompt (`app/Services/TargetedResumeService.php` ~L803) to list `withdrawn` and the example "I withdrew my application" → `status=withdrawn`. Add or extend a tool test (see how `tests/Feature/ApplicationCoreSmokeTest.php` drives `update-status`) showing `status=withdrawn` is accepted and sets the application to `withdrawn`. Verify with `--filter`.
- [x] 2.5 If any existing test asserts the full status list (filters payload in `ApplicationListTest`, payload snapshots, etc.), update it for the tenth status. Verify by running `php artisan test --compact --filter=Application`.

## 3. Metrics

- [x] 3.1 In `app/Services/ApplicationMetricsService.php`: make `outcome()` return `withdrawn` (after the accepted/hired and rejected checks), add `'withdrawn' => 'Withdrawn'` to the `outcomes()` labels, change the `toFirstResponse` lookup in `cycleTimes()` to skip `withdrawn` as well as `applied`, and update the `STAGE_RANKS` docblock to name `withdrawn` as an off-ramp alongside `rejected`.
- [x] 3.2 Add tests to `tests/Feature/ApplicationMetricsTest.php` for each scenario in the metrics delta spec: `applied → withdrawn` lands in a "Withdrawn" outcome bucket and not rejected/ghosted/in-progress; it stays in Total Applied and the rate denominators; it doesn't raise the response rate and contributes nothing to `toFirstResponse`; `applied → interviewing → withdrawn` counts as responded and interviewed, reaches Interviewing in the funnel, has outcome `withdrawn`, and its first response is measured to `interviewing`; its timeline outcome is `withdrawn` and its last segment ends at the withdrawal date. Verify with `php artisan test --compact tests/Feature/ApplicationMetricsTest.php`.

## 4. Frontend

- [x] 4.1 Add `case "withdrawn":` to the `secondary` branch of `resources/js/admin/utils/statusColor.ts`, and a `withdrawn` → `theme.palette.secondary.main` case to `resources/js/admin/utils/statusChartColor.ts`. Verify `npm run build` succeeds, and that ESLint/Prettier are clean on both files (fix any formatting in them before committing).
- [x] 4.2 Manually check in the local admin (Vivaldi, Docker site): on an `interviewed` application's Discussion page the status-history form offers "Withdrawn"; logging it shows a secondary, outlined chip, and the form then offers no next status; the Applications list filter offers `withdrawn` and filters to it; the Metrics Outcomes chart shows a Withdrawn slice in the secondary color and the Timeline row shows a `withdrawn` chip. Take screenshots for the developer before merging.

## 5. Docs and wrap-up

- [x] 5.1 Update the *Status* section of `.claude/context/applications.md`: add `withdrawn` to the enum list and to the terminal set, note that it's offered from `applied` through `offered`, and record the metrics rules (own outcome, not a response, kept in the denominators, skipped for first response). Verify by reading the section back.
- [x] 5.2 Run the application-related tests (`php artisan test --compact --filter=Application` plus `tests/Unit/Enums/ApplicationStatusTest.php`) and confirm they're green, then ask the developer whether to run the full suite.
