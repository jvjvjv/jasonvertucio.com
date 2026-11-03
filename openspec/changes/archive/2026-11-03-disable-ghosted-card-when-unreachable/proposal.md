## Why

An application only counts as ghosted after `resume.ghosted_after_days` of silence (30 by default), so on the Last 30 days filter the Ghosted card shows a figure that cannot mean anything: nothing in the period has had time to go ghosted. It reads as a real 0%, which suggests no one has ghosted the owner, when the honest answer is that it is too soon to tell.

## What Changes

- The Ghosted KPI card is greyed out, showing "—" and a short reason, whenever the selected period starts too recently for its applications to have reached the ghosted threshold.
- The rule follows the configured threshold and the period's start date, not the "30d" preset by name. It therefore also covers custom ranges, and a changed threshold moves it (at 60 days, Last 30 days is disabled and Last 90 days is not; at 14 days, neither is).
- The server reports no ghost rate for such a period, plus a separate signal that the rate does not apply, so the page can tell "too recent" apart from "nothing was applied to".
- The card stays in place rather than disappearing, so the KPI row does not reflow when switching presets.
- Unchanged: how "ghosted" is judged for an individual application, the other KPI cards, the Outcomes chart and the Timeline.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `application-metrics`: adds a requirement for when the ghosted rate is available and how the card looks when it is not.

## Impact

- `app/Services/ApplicationMetricsService.php`: decides availability, and adds a new top-level prop to the dashboard payload.
- `resources/js/admin/pages/resume/metrics/Index.tsx`: disabled state on the card.
- `resources/js/admin/pages/resume/metrics/MetricsFilterBar.tsx`: the new prop must join the partial-reload list.
- `tests/Feature/ApplicationMetricsTest.php`, `tests/Feature/ApplicationMetricsHttpTest.php`: new coverage.
- `.claude/context/applications.md`: note the rule alongside the "Ghosted" description.
- No migration, dependency or route changes.
