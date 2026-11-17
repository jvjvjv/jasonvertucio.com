## Context

- **The review page is Blade, not Inertia.** `ResumeController::index` handles `?revision={id}`. It aborts with 403 unless the viewer `can('edit-resume')`, loads `ResumeEditCandidate::snapshot`, and renders `resources/views/resume/index.blade.php` through `x-resume.header|skills|experience|education|projects`. Those components already tolerate a malformed snapshot: a single entry where a list belongs, or missing fields. Alpine is bundled (`resources/js/app.js`), and `resume.css` is loaded by the page.
- **Snapshot shape** is `DatabaseResumeDataService::getAllEditableData()`: `personal`, `skills{top,other}[]{title,list}`, `experience[]` (with `jobTitle`, `company`, `location`, `dates`, `bullets`, plus editor-only `jobTitleLabel`, `salaryStart`, `salaryEnd` and `isFreelance`), `education[]` (editor dates) and `projects[]`.
- **A candidate is seeded** from the latest pending candidate's snapshot, or from the live data. So revision N's snapshot already contains revision N−1's edits.
- **Older versions keep their rows.** Each `ResumeVersion` owns its personal info, skills, experiences, educations and projects. `getAllEditableData()` only reads the current version, through `getCurrentVersion()`.
- **Drivers.** `ResumeDataServiceContract` is bound per `config('resume.driver')`. Production uses `database`. Candidates and versions exist only in the database.
- **Text rendering.** Summary and skills print through `SafeInlineHtml::clean()` (bare `b`, `strong`, `i`, `em`, `br`). Experience and project bullets are escaped (`{{ }}`).
- **The MCP list tool** (`ListPendingResumeCandidatesTool`) lists pending candidates whose base is the live version, so base and live are the same version for it.
- `sebastian/diff` 7.0.1 is locked under `packages-dev`, pulled in by PHPUnit 12 (`^7.0`). This change promotes it to `require`. `Differ::diffToArray()` accepts token arrays and returns `[token, op]` pairs (`OLD`, `ADDED`, `REMOVED`), choosing its time- or memory-efficient LCS method on its own.

## Goals / Non-Goals

**Goals:**
- One diff engine whose output feeds the Changes panel, the inline rendering and the MCP tool, so all three always agree.
- Leave the public `/resume` page and the existing resume components untouched.
- No schema changes. The only dependency change is promoting the already-locked `sebastian/diff` to `require`.

**Non-Goals:**
- Diffing the DOCX or PDF output, or the plain-text and JSON responses.
- Side-by-side layout.
- Comparing any two arbitrary revisions. Only the three baselines are offered.
- Accepting or rejecting changes one at a time. Approve and reject stay whole-revision.
- A `compare` argument on the MCP tool. It always compares against the base version, which is the live one.

## Decisions

### 1. Load a version's editable data without making it current

`DatabaseResumeDataService::getAllEditableData(?ResumeVersion $version = null)` loads the given version, or the current one when none is given. `ResumeDataServiceContract` and `JsonResumeDataService` gain the same optional parameter. The JSON driver has no versions, so it ignores the parameter.

The diff code depends on `DatabaseResumeDataService` directly rather than on the contract, because baselines are database versions by definition.

*Alternative:* a separate loader that duplicates the `transform*` methods. Rejected, because the two would drift and produce false differences.

### 2. Baseline resolution: `ResumeDiffBaseline`

`App\Services\Resume\Diff\ResumeDiffBaseline::resolve(ResumeEditCandidate $candidate, ?string $requested)` returns `{mode, label, data, available: [modes], fell_back: bool}`.

| Mode | Data source | When it is available |
| --- | --- | --- |
| `base` | `getAllEditableData($candidate->baseResumeVersion)` | Always. If the base version row is missing, the baseline is empty data and the label says so |
| `live` | The current version | A current version exists and its id ≠ `base_resume_version_id` |
| `previous` | The `snapshot` of the highest `revision_number` below this one with the same base | Such a candidate exists, pending or approved |

An unknown mode or an unavailable one resolves to `base` with `fell_back = true`.

### 3. The diff model: `ResumeSnapshotDiffer`

`diff(array $before, array $after): ResumeDiff` produces a tree. Each section holds entries. Each entry has a `status` (`added`, `removed`, `modified`, `unchanged`), a `moved` flag and a `label`. Each entry holds field changes, and a field change has a `field`, a `kind`, `before`, `after`, a `shown` flag and `tokens` (the word-diff segments for text). The tree has two consumers:
- `changes()` flattens it into the list used by the panel and the MCP tool;
- the inline components walk the tree.

**Normalization.** Both sides first go through the same tolerant shaping the components use: a non-list section is wrapped as a single entry, non-array entries are dropped, and missing, null and `''` compare equal. Strings are compared after trimming. That is why a malformed draft can't throw.

**Entry identity**, normalized to lowercase and trimmed:

| Entry | Identity |
| --- | --- |
| Experience | `company` + start date |
| Education | `institution` + `degree` |
| Projects | `projectName` |
| Skill categories | group (`top` or `other`) + `title` |

Matching works in two passes:
1. Match entries by identity, independent of position.
2. Pair the leftovers. Each unmatched removed entry is paired with an unmatched added entry in the same section when the share of equal fields is at least ½. Such a pair is reported as modified, which catches a renamed company or project. Anything still unmatched is added or removed.

`moved` is set for matched entries that are not on the longest increasing subsequence of their old indices. That is the minimal set of entries to call "moved".

**Bullets (string lists).**
1. `Differ::diffToArray()` on the two arrays of trimmed bullet strings anchors the unchanged bullets.
2. Inside each gap between anchors, removed and added bullets are paired greedily by word-set Jaccard similarity of at least 0.5, and each pair becomes a modified bullet with word tokens.
3. Leftovers are added or removed.

**Skills (`list`):** case-insensitive set difference for added and removed. When the shared items appear in a different order, one `order_changed` change is reported.

**Text diff: `WordDiff::tokens(string $before, string $after)`.** It tokenizes into words, whitespace runs and punctuation. Allowed inline tags (`<b>` and the like) are stripped before tokenizing, and a change that is purely formatting is reported as a field change with the kind `formatting`. It then aligns the token arrays with `(new Differ(new UnifiedDiffOutputBuilder))->diffToArray($fromTokens, $toTokens)` and maps `OLD`/`ADDED`/`REMOVED` to `equal`/`insert`/`delete`. The output is a list of `{op: equal|insert|delete, text}` segments with adjacent same-op segments merged.

*Alternative:* an in-house LCS. Rejected: the library is already vetted and locked, and it handles the memory/time trade-off itself. Similarity pairing within gaps and the LIS for "moved" stay custom, because the library does neither.

**Hidden fields.** `shown = false` for these fields:
- Experience: `jobTitleLabel`, `salaryStart`, `salaryEnd`, `isFreelance`.
- Personal: `email`, `phone`, `linkedin`, `url`.

Dates count as shown, because the review page renders the snapshot's dates as they are.

Salary values are formatted as `amount / period` for display.

### 4. Rendering: both versions in the page, toggled by Alpine

When a revision is shown, `index.blade.php` renders the following:
1. **The baseline selector.** A small `<form method="GET">` with a `<select name="compare">` that lists only the available modes and keeps `revision` as a hidden field. It submits on change, with a submit button as the no-JS fallback. Beside it are the "Compared with: …" label and the fallback notice.
2. **`<x-resume.diff.changes-panel :diff>`**, always visible.
3. **A wrapper** `<div x-data="resumeDiffToggle" …>` holding:
   - the "Show changes" checkbox;
   - `<div x-show="show">`, the marked-up rendering through the new `x-resume.diff.*` components (`header`, `skills`, `experience`, `education`, `projects`), which walk the diff tree;
   - `<div x-show="!show" x-cloak>`, the existing components given the snapshot exactly as today.

`resumeDiffToggle` is registered in `resources/js/app.js` as `Alpine.data`. It reads and writes `localStorage['resume-diff-show']` inside try/catch, and defaults to `true`. Without JS, the marked-up view shows and the clean one stays hidden by `x-cloak`, which is acceptable because the reviewer is an admin.

**Why two renderings instead of one marked-up DOM toggled by CSS:** the clean view must equal today's rendering. Reusing the existing components guarantees that, and keeps the diff components free of a "clean mode". A resume is small, so rendering it twice costs nothing meaningful.

**Markup.**
- Word changes use `<ins class="rd-ins">` and `<del class="rd-del">`.
- Added or removed entries and bullets get a wrapper class `rd-added` or `rd-removed`, plus a visually hidden "Added:" or "Removed:" prefix for screen readers.
- Moved entries get a small "Moved" tag.
- Styles go in `resume.css`:
  - `rd-ins`: underline and a tinted background from the theme tokens;
  - `rd-del`: line-through and a tinted background.

  There are no gradients or shadows. Underline and strike-through carry the meaning without colour.
- Diff text is always escaped. Fields that normally go through `SafeInlineHtml` are diffed on their tag-stripped text, and each token is escaped individually. Untrusted snapshot text never reaches the page as markup, and `<ins>`/`<del>` can't split an inline tag.
- Print styles keep the marks, since the reviewer may print a marked-up copy.

**Changes panel.** Changes are grouped by section in resume order, with a count in the heading. Each row shows:
- the entry label (for example "Acme — Senior Engineer");
- the field name ("Bullet 3", "Salary (start)");
- a kind badge;
- "Not shown on resume" when applicable;
- the before → after values, or the word tokens for text.

Rows for removed and added entries summarize the entry rather than listing every field.

### 5. Controller wiring

`ResumeController::index`, when a candidate is present and the response is HTML, resolves the baseline from `$request->query('compare')` and diffs the baseline data against `$candidate->snapshot`. It passes `diff`, `baseline` (mode, label, available modes, fell_back) to the view. The JSON and plain-text branches return before this, so they are unchanged.

The diff runs only on the authorized revision path, after the existing `abort_unless(…, 403)`.

### 6. MCP tool output

`ListPendingResumeCandidatesTool` eager-loads `snapshot` and, once, the live version's editable data. Each candidate gains:

```
change_count: int,
changes: [{ section, item, field, kind, before, after, shown }]
```

`before` and `after` are plain strings, using the same formatting as the panel. Word tokens are omitted, since the model needs the values and not the markup. Moved and order-changed changes have null `before` and `after`. The description adds: "Each candidate includes `changes` compared with the live resume, so you can tell the user what a revision changes before approving it."

## Risks / Trade-offs

- **[Spurious diffs from transform drift]** A snapshot taken before a change to the `transform*` output, such as a new key, would differ from freshly loaded base data. → The differ compares only the known fields listed above, and treats missing and empty as equal.
- **[Identity heuristics misfire]** For example, the persona changes a company name and its start date together. → The similarity pairing still reports a modified entry when at least half the fields are equal. Otherwise the reviewer sees a removal plus an addition, which is still correct, just less tidy.
- **[Quadratic word diff on a huge field]** A pathological summary of thousands of words. → Tokens are capped per field at 2,000. Above the cap the field is reported as replaced (before → after) without word tokens.
- **[MCP response size]** Long summaries in `before` and `after` for several candidates. → Acceptable at the expected one to three pending candidates. The text is the useful part.
- **[Stale baseline confusion]** "base" on a stale draft shows changes relative to an old version. → The stale warning stays, the panel names the baseline, and "live" is offered exactly in that case.

- **[A future PHPUnit major requires `sebastian/diff ^8`]** → Widen the constraint (`^7.0 || ^8.0`) during that upgrade; `WordDiff`'s tests are the check, and `diffToArray()`'s op constants have been stable across majors.

## Migration Plan

This needs a code deploy with `composer install` (which now installs `sebastian/diff` in production too) and `npm run build`, for the CSS and the Alpine component. There is no data migration. Rolling back is a code revert.
