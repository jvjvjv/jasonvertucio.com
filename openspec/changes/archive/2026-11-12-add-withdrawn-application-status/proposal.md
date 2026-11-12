## Why

When I pull out of a hiring process after applying (I took another offer, the role changed, I lost interest), the tracker has no way to say so. Today the closest options are `rejected`, which wrongly says the employer turned me down and skews the response and rejection metrics, or leaving it `applied`, where it eventually shows as "ghosted" and counts against the employer. A `withdrawn` status records that the decision was mine.

## What Changes

- Add `withdrawn` to `ApplicationStatus`: a pipeline status (it can only follow `applied`), and terminal, like `rejected`.
- `withdrawn` is offered as a next status from `applied`, `interviewing`, `interviewed` and `offered`, so it appears in the Discussion page's status-history form and can be logged with a date and notes.
- The AI session's `update-status` tool accepts `withdrawn`, and its description and the targeted-resume system prompt tell the agent that "I withdrew my application" maps to it.
- A withdrawn application is never displayed as "ghosted" (that only ever applies to `applied`).
- Application Metrics treats `withdrawn` as its own outcome:
  - a new **Withdrawn** bucket in the Outcomes chart and on the Timeline;
  - withdrawing does **not** count as an employer response, so it doesn't raise the response rate and isn't counted as the "first response" in cycle times;
  - withdrawn applications stay in the Total Applied count and in the rate denominators, because they were real applications.
- Status chip and chart colors get an entry for `withdrawn`.
- Nothing changes in the database schema: `status` is already a string column.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-tracking`: the list of application statuses gains `withdrawn` (pipeline, terminal), and a new requirement covers withdrawing from an application.
- `application-metrics`: a new requirement covers how withdrawn applications count in outcomes, response rate and cycle times.

## Impact

- **Backend**: `app/Enums/ApplicationStatus.php`, `app/Services/ApplicationMetricsService.php`, `app/Services/Mcp/Tools/TargetedResume/UpdateStatusTool.php` (description only; the enum list comes from `pipeline()`), the `update-status` line in `TargetedResumeService`'s system prompt, and the `TerminalApplicationException` docblock.
- **Frontend**: `resources/js/admin/utils/statusColor.ts` and `statusChartColor.ts`. The status-history form, the list's status filter and the Timeline already work from server-supplied status lists, so they need no code changes.
- **Docs**: the *Status* section of `.claude/context/applications.md`.
- **Tests**: `tests/Unit/Enums/ApplicationStatusTest.php` (it pins the exact case list, so it must be updated), plus new coverage in the status-update, MCP tool and metrics tests.
- **Data**: no migration and no backfill. Existing applications are unchanged.
