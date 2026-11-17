All PHP tests use `DatabaseTransactions` (never `RefreshDatabase`) and run against the `wink` test database. No migrations; one dependency promotion (task 1.0).

## 1. Version data loading

- [x] 1.0 Run `composer require sebastian/diff:^7.0`. Verify that `composer.json` lists it under `require` and the lock keeps 7.0.1, now under `packages`.
- [x] 1.1 Give `getAllEditableData()` an optional `?ResumeVersion $version = null` on `ResumeDataServiceContract`, `DatabaseResumeDataService` (load that version, or the current one when null) and `JsonResumeDataService` (accepts the parameter and ignores it). Add a test that loading a non-current version returns that version's data and leaves `is_current` untouched. Verify it and `ResumeEditCandidateServiceTest` pass.

## 2. Diff engine (`app/Services/Resume/Diff/`)

- [x] 2.1 Create `WordDiff::tokens(string $before, string $after)`. It tokenizes words, whitespace and punctuation, strips the allowed inline tags first, aligns tokens with `Differ::diffToArray()`, merges adjacent same-op segments, and caps a field at 2,000 tokens (falling back to a whole replacement). Add a unit test and verify it passes. The test covers:
  - a single word swap;
  - an insertion;
  - a deletion;
  - identical strings;
  - a formatting-only change;
  - the cap;
  - the op mapping from `diffToArray()`.
- [x] 2.2 Create `ResumeSnapshotDiffer::diff(array $before, array $after): ResumeDiff`. It covers:
  - tolerant normalization (non-list section wrapped, non-arrays dropped, missing, null and empty equal);
  - entry identity per section;
  - leftover pairing at ½ field similarity;
  - LIS-based `moved`;
  - bullet alignment via `diffToArray()` with Jaccard pairing at 0.5 or more;
  - case-insensitive skill set diff and `order_changed`;
  - `shown = false` for the hidden fields;
  - salary formatting.

  Add `ResumeDiff::changes()` (the flat list) and the tree accessors the views need. Add a unit test for every scenario in the spec's "Differences are identified…" and "Changes to fields the resume does not show…" requirements, and verify it passes.
- [x] 2.3 Create `ResumeDiffBaseline::resolve(ResumeEditCandidate, ?string)`. It covers:
  - the `base`, `live` and `previous` modes;
  - their availability rules;
  - the fallback to `base` with `fell_back`;
  - a missing base version (empty data plus a label).

  Add a feature test for each baseline scenario in the spec and verify it passes.

## 3. Review page

- [x] 3.1 In `ResumeController::index`, on the authorized HTML revision path only, resolve the baseline from `compare`, compute the diff and pass `diff` and `baseline` to the view. Extend `ResumeEditCandidateReviewTest` and verify it passes. The test asserts:
  - the default `base` mode;
  - `compare=live` on a stale draft;
  - `compare=previous`;
  - the fallback notice;
  - 403 for an unauthorized viewer;
  - unchanged JSON and plain-text responses;
  - `/resume` without a revision has no panel or toggle.
- [x] 3.2 Create `resources/views/components/resume/diff/changes-panel.blade.php`: the count heading, the baseline label, rows grouped by section (entry label, field, kind badge, "Not shown on resume", before → after or word tokens), and the "matches the baseline" empty state. All text is escaped. Verify with a view test that a reworded bullet plus an added skill renders 2 changes under Experience and Skills.
- [x] 3.3 Create the marked-up components `resources/views/components/resume/diff/{header,skills,experience,education,projects}.blade.php`. They walk the diff tree, with `<ins class="rd-ins">` and `<del class="rd-del">` for words, `rd-added` and `rd-removed` wrappers with screen-reader prefixes for entries, bullets and skills, and a "Moved" tag. Their layout matches the existing components. Verify with a view test that a removed project renders inside `rd-removed` and a reworded bullet contains both `rd-del` and `rd-ins`.
- [x] 3.4 Update `resources/views/resume/index.blade.php` for revisions: the baseline `<form method="GET">` selector (available modes only, a hidden `revision`, a submit fallback), the "Compared with" label and fallback notice, the Changes panel, and the `x-data="resumeDiffToggle"` wrapper with the marked-up view (`x-show="show"`) and the existing components (`x-show="!show" x-cloak`). Verify with the 3.1 test assertions.
- [x] 3.5 Register `Alpine.data('resumeDiffToggle', …)` in `resources/js/app.js`: default `true`, persisted to `localStorage['resume-diff-show']` inside try/catch. Add the `rd-*` styles and print rules to `resources/css/resume.css`, with no gradients or shadows; meaning comes from underline and strike-through. Verify that `npm run build` succeeds.
- [x] 3.6 Do a design pass and visual check (frontend-design skill). Load a local revision with a reworded bullet, an added and a removed job, a moved project, a skill change and a salary change. Check that the toggle switches views and is remembered after reload, that the selector changes the URL, and that the page works at phone width. Capture screenshots for the developer before merging.

## 4. MCP tool

- [x] 4.1 Make `ListPendingResumeCandidatesTool` load the live version's editable data once and add `change_count` and `changes` (`section`, `item`, `field`, `kind`, `before`, `after`, `shown`; plain strings, no tokens) per candidate. Update its description. Extend `tests/Unit/Services/Mcp/Tools/ChatBot/ResumeEdit/ListPendingResumeCandidatesToolTest.php` and verify it passes. The test covers:
  - two changes described;
  - an identical candidate with 0;
  - a salary change flagged as not shown;
  - the existing listing and empty cases still passing.

## 5. Docs and review

- [x] 5.1 Update the *AI-Persona Resume Editing* section of `.claude/context/resume.md`: the `compare` baselines, the diff engine location, the Changes panel and toggle, and the tool's `changes` output. Verify by reading it back.
- [x] 5.2 Fix Prettier and ESLint issues in the files this change touched (`app.js`), so the husky hook passes. Run the `laravel-reviewer` agent over the diff and address its findings. Run `php artisan test --compact --filter='Resume'` and verify it is green. Ask the developer whether to run the full suite.
