## Context

`App\Enums\ApplicationStatus` is the only status enum, and most of the system already derives from it:

- `pipeline()` (everything except `draft`/`passed`) drives `StoreApplicationStatusUpdateRequest`'s `Rule::in` and the `update-status` MCP tool's schema enum.
- `allowedNext()` feeds `ApplicationPayload::allowedNextStatuses()`, which is what the Discussion page's status-history form offers (minus `applied`, which only Mark Applied records).
- `isTerminal()` drives `ApplicationService::assertNotTerminal()` and the empty next-status list.
- `ApplicationStatus::cases()` drives the list's status filter.
- `applications.status` and `application_status_updates.status` are `string(50)` columns, so a new value needs no migration.

What does **not** derive from the enum is `ApplicationMetricsService`, which works on raw string values (`'rejected'`, `'offered'`, …), the two JS color maps, the hardcoded status list in the `update-status` tool's `#[Description]`, and the matching line in `TargetedResumeService`'s system prompt.

## Goals / Non-Goals

**Goals:**

- `withdrawn` behaves like `rejected` in the state machine (a terminal off-ramp from anywhere in the pipeline), but metrics report it separately.

**Non-Goals:**

- No dedicated "Withdraw" button in the Discussion header. The status-history form already logs terminal entries (that's how `rejected` is recorded today), so a header button would be a second path to the same write. Easy to add later if it turns out to be wanted.
- No change to Pass. Pass stays the pre-application off-ramp and is still refused once the application is in the pipeline.
- No change to the discard-targeted-resume guard, which keys on an `applied` entry and is unaffected.
- No "declined offer" status. Declining an offer is recorded as `withdrawn` from `offered`, with the reason in the notes.

## Decisions

### `withdrawn` is a pipeline status, not a pre-pipeline one

You can only withdraw an application you've submitted. Making it a pipeline status means it's a history entry with a date and notes, the request and MCP validation pick it up for free, and metrics see it. The alternative, a sibling of `passed` set by its own endpoint, would leave it without a date or history and keep it out of metrics. That loses exactly the information this change exists to record.

### Terminal, offered from `applied`, `interviewing`, `interviewed`, `offered`

This matches `rejected`'s reach and adds `interviewing`, which `rejected` already has too. Terminal means an accidental entry is undone by deleting it, which already restores the previous status (`deleteStatusUpdate`). A new withdrawal endpoint isn't needed.

### Metrics: its own outcome, not a response, still in the denominators

- **Outcome bucket.** `outcome()` currently returns `accepted`, `rejected`, `ghosted` or `in_progress`. Add `withdrawn`, checked after `accepted`/`hired` and `rejected`, so if a history somehow held both terminal entries the employer's decision would win. In normal use only one terminal entry can exist, because a terminal application refuses new entries. Add `'withdrawn' => 'Withdrawn'` to the outcome labels.
- **Not a response.** `responded` stays `maxRank >= 2 || rejected`. Withdrawn has no stage rank (same as `rejected`) and isn't in that `||`, so an `applied → withdrawn` application doesn't count as a response, while `applied → interviewing → withdrawn` still does, through its rank.
- **Cycle times.** `toFirstResponse` takes the first entry whose status is not `applied`. That entry is changed to also skip `withdrawn`, or a self-withdrawal would be timed as the employer's reply. `toRejection` and `toOffer` already match exact statuses and are unaffected.
- **Denominators.** Withdrawn applications stay in `totalApplied` and in every rate's denominator. The alternative, removing them from interview and offer rates, would make "rates over applications that ran their course" a different population from Total Applied, and the dashboard has no way to explain that. Keeping one population is simpler and honest, and the Withdrawn bucket shows how big the effect is.
- **Timeline.** It already ends a terminal segment at its own date (`isTerminal()`), so nothing changes beyond the outcome chip.

### Colors: `secondary`, next to `passed`

Both are "I chose not to continue", which differs from the employer's `rejected` (error/red) and from `ghosted` (grey). Chip: `secondary`, outlined (it's not in `StatusChip`'s `FILLED_STATUSES`, like `rejected`). Chart: `theme.palette.secondary.main`, added to `statusChartColor`, which today has no `passed` entry because pre-pipeline applications never reach the charts.

### Agent-facing text

The `update-status` tool's schema enum updates itself through `pipeline()`. Its `#[Description]` and the `update-status` line in the `TargetedResumeService` system prompt list the statuses by hand. Both get `withdrawn` and the example `"I withdrew my application" → status=withdrawn`. The tool's name and response keys are part of the prompt contract and don't change.

## Risks / Trade-offs

- [Stored system prompts] The targeted-resume prompt text in `TargetedResumeService` may also be stored on prompt records or in conversations' saved `system` messages. Existing sessions keep their old prompt (saved `system` messages are never rewritten), so the agent there learns about `withdrawn` only from the tool's schema enum and description, which are always current. → Acceptable. The tool schema is what's enforced.
- [Exact-list tests] `ApplicationStatusTest` pins the case list and provider tables, and other tests may assert the full filter or payload lists. → Update them in the same change. Expect to touch several, not one.
- [JS label] The status-history form title-cases the raw value ("Withdrawn"), and the Timeline chip shows `row.outcome.replace("_", " ")` ("withdrawn"). Both are fine without new label maps.

## Migration Plan

No schema or data migration. Deploy is the normal process (build assets, clear caches, restart the queue worker per CLAUDE.md). Rolling back is a code revert. Any rows already saved as `withdrawn` would then fail to cast to `ApplicationStatus` and have to be reset by hand first, so roll back only before anything has been withdrawn, or update those rows to `rejected` beforehand.
