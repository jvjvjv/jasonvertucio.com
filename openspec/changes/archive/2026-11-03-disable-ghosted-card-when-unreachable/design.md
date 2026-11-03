## Context

See `proposal.md` for motivation and `specs/application-metrics/spec.md` for the required behavior.

The period resolves server-side in `ApplicationMetricsRequest::period()`, and `ApplicationMetricsService::build($from, $to)` computes every section. An application is ghosted when its latest entry is `applied` and older than `now - threshold`, compared to the second (`ApplicationStatusResolver::resolve`).

Three details of the existing code shape this design:

- `Last 30 days` starts at `today - 30 days` at 00:00, while the ghost cutoff is `now - 30 days`. At noon, an application from the morning of the period's first day has already passed the cutoff. **So on any day, the 30-day window can hold a few genuinely ghosted applications, and a second-precise "can anything be ghosted?" test would almost never disable the card on the very preset this change is about.**
- `MetricsFilterBar` reloads with Inertia's `only: PERIOD_PROPS`. A new top-level prop left out of that list goes stale when the preset changes.
- Tests compare `kpis` to an exact array (`ApplicationMetricsHttpTest::test_custom_range`, `test_period_with_no_applications` and others), so adding a key inside `kpis` would break them for no benefit.

## Goals / Non-Goals

**Goals:**

- Decide availability in one place, on the server, from the threshold and the period's start.
- Grey the card out without changing the layout of the KPI row.

**Non-Goals:**

- Changing how a single application is judged ghosted, or `ApplicationStatusResolver`.
- Changing what the rolling presets cover. "Last 30 days" really spans 31 calendar dates, but that is a separate question, and tests pin it.
- A shared disabled-card component. `StatCard` is local to this page.

## Decisions

### Grey the card out rather than hide it

The request allowed either. Greying keeps five cards in the row: `StatCard` uses `flex: 1 1 160px`, so removing one would resize its neighbours on every switch between presets. It also explains the absence, where a missing card could look like a bug.

### Judge availability by whole days against the period's start

The rate does not apply when `from` (start of day) is on or after `today - threshold`:

```php
private function ghostRateApplicable(?CarbonInterface $from, int $ghostedAfterDays): bool
{
    return $from === null
        || $from->copy()->startOfDay()->lessThan(Carbon::today()->subDays($ghostedAfterDays));
}
```

Whole days match how periods are defined, so the answer stays the same all day rather than changing with the time of the request. The `to` date plays no part, because ghosting is judged against the present.

Alternatives considered:

- *Match on `range === '30d'`.* Rejected: it misses custom ranges and breaks when the threshold changes.
- *Second-precise comparison against `now - threshold`.* Rejected for the reason in Context.
- *Disable only when the computed ghost count is zero.* Rejected: the card would flicker on and off depending on the time of day and on what was applied to a month ago, and would still show a misleading figure from a sliver of one day.

### Send `ghostRate: null` plus a new top-level `ghostRateApplicable` boolean

`kpis.ghostRate` becomes `null` when the rate does not apply, so no number exists to render by mistake. `null` alone cannot be the signal, because it already means "nothing was applied to", and that case should not be greyed out or claim the period is too recent.

The boolean sits at the top level beside `ghostedAfterDays`, not inside `kpis`, to keep the exact `kpis` assertions meaningful. It joins `PERIOD_PROPS`, since unlike `ghostedAfterDays` it changes with the period.

### Disabled look uses existing theme values only

`StatCard` gains an optional `disabledReason?: string`. When set:

- The card gets `aria-disabled="true"`.
- The value and label use `color="text.disabled"`, already used across the admin.
- The reason renders below as a `caption` in `text.secondary`, so the explanation stays readable while the figure recedes.

No hard-coded colours (ESLint rejects them under `resources/js/admin/`), no shadows or gradients. The reason reads "Too recent to measure", short enough for one line at the card's minimum width. The label keeps its `Ghosted (>30d)` form, which supplies the threshold.

The reason text adds a line, so the row grows slightly while the card is disabled. The design pass in `tasks.md` checks this at 1280 and 360 pixels; if it looks wrong, the fix stays within `StatCard`.

## Risks / Trade-offs

- [The Outcomes chart can show a Ghosted slice while the card is greyed out, when an application from the period's first morning has passed the cutoff.] → Accepted and specified. Those applications really are ghosted and show that way on the Applications list too, so the chart stays truthful; what is withheld is a rate that a one-day sliver cannot support.
- [A new prop left out of `PERIOD_PROPS` would leave the card stale after switching presets, and PHPUnit cannot see it.] → There is no JS test runner, so the browser check in `tasks.md` covers switching presets in both directions.

## Migration Plan

Code only: no migration or queue impact, and no worker restart needed. Deployment stays with the developer through the normal process.
