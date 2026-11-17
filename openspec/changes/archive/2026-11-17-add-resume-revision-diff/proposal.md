## Why

When I review an AI-drafted resume revision at `/resume?revision={id}`, the page renders the whole draft and nothing marks what the persona changed. Finding the edits means reading the entire resume against my memory of the live one. That makes approving risky, especially for fields the page doesn't even show, like salary or contact details. The persona reviewing drafts from chat is in the same position: `list-pending-resume-candidates` returns revision numbers and timestamps, so it can't tell me what a revision changes before approving it.

## What Changes

- **A structured diff between two resume snapshots**, computed on the server and used by both the review page and the MCP tool. It covers every section a draft can change: personal info, skills, experience, education and projects.
  - **Entries** (jobs, projects, schools, skill categories) are matched by identity, not position. They are reported as added, removed, modified or moved.
  - **Bullets** are aligned, and a reworded bullet is reported as modified with a word-level diff, not as a removal plus an addition.
  - **Skills** within a category are reported as added or removed, plus an "order changed" note.
  - **Fields the resume doesn't render** (salary, job-title label, freelance flag, email, phone, LinkedIn, URL) are included and marked "not shown on the resume".
- **The review page shows the diff two ways:**
  - **A Changes panel** above the resume, grouped by section, with a change count. Each change shows before → after, and a modified text field gets word-level highlighting. When nothing differs, the panel says so.
  - **Inline tracked changes** in the rendered resume: inserted words and added entries are marked as insertions, removed ones as deletions, and moved entries are tagged.
- **A "Show changes" toggle** switches the rendered resume between the marked-up draft and the clean draft. It is on by default and remembered per browser. The Changes panel is always shown.
- **A selectable baseline.** The page compares the draft against one of three things, chosen with a `compare` control reflected in the URL (`?revision={id}&compare=base|live|previous`):
  - **base** (default): the version the draft branched from, showing everything the persona proposed;
  - **live**: the current resume, showing what approving would change. Offered only when the draft is stale, since otherwise it's identical to base;
  - **previous**: the nearest earlier revision that still exists for the same base version, showing only what this revision added. Offered only when such a revision exists.

  An unknown or unavailable `compare` value falls back to base, with a notice.
- **`list-pending-resume-candidates` returns each candidate's changes against its base version** (which is the live version, since the tool lists only those). It adds a `change_count` and a `changes` list of section, item, field, kind, before and after, so the persona can describe a revision before approving it. The tool's description says so.
- Approving, rejecting, the stale warning and the plain-text and JSON responses for `?revision=` are unchanged.

## Capabilities

### New Capabilities

- `resume-revision-diff`: comparing a draft revision with a chosen baseline, the Changes panel, inline tracked changes, the Show changes toggle, and the baseline selector on the review page.

### Modified Capabilities

- `resume-candidate-review-mcp-tools`: "A tool lists every pending candidate for the live resume version" adds each candidate's change list and change count.

## Impact

- **Backend:**
  - new `app/Services/Resume/Diff/`:
    - a snapshot differ;
    - a word-level text diff built on `sebastian/diff`'s `Differ::diffToArray()`;
    - a baseline resolver;
  - `DatabaseResumeDataService` can build editable-shape data for a given `ResumeVersion`, not just the current one;
  - `ResumeController::index` accepts `compare` and passes the diff and baseline options to the view;
  - `ListPendingResumeCandidatesTool` gains `changes` and `change_count`.
- **Views:**
  - new `resources/views/components/resume/diff/` components (Changes panel, marked-up sections);
  - `resources/views/resume/index.blade.php` gains the baseline selector, the toggle (Alpine) and both renderings when a revision is shown;
  - diff styles go in `resources/css/resume.css`.
  - The existing resume components, and so the public resume, are not changed.
- **Dependencies:** `sebastian/diff` (`^7.0`, already locked at 7.0.1 through PHPUnit) is promoted from a dev-only dependency to `require`. No database changes.
- **Docs:** the *AI-Persona Resume Editing* section of `.claude/context/resume.md`.
