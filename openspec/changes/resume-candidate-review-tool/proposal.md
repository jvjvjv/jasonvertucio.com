## Why

An authorized AI Persona can already draft resume edits via `update-resume-section` and see whether *a* revision is pending via `get-resume-data`'s `pending_revision_number` field, but there is no MCP tool that lists every pending candidate for the live resume with enough detail to review, and no MCP tool that approves one — approval only exists today as a web form on `/resume?revision=`. Approval is also rigid: it always bumps the patch segment of the current version (`YYYY.MAJOR.MINOR` → `YYYY.MAJOR.MINOR+1`), with no way to publish a candidate as a major/minor release. This change adds MCP tools so an authorized persona (gated the same way `update-resume-section` is) can view pending candidates and approve one with a chosen version, entirely from the chat interface.

## What Changes

- Add an MCP tool that lists every `pending` resume edit candidate for the current live resume version (revision number, last-edited time, and a version suggested for approval), so a persona can tell the user what's awaiting review without already knowing a revision number.
- Add an MCP tool that approves a `pending` candidate, taking the candidate's revision number and the version to publish it as. The supplied version SHALL be validated (`YYYY.MAJOR.MINOR` format, strictly greater than the base version) the same way whether approval comes from this tool or the existing web form.
- Change candidate approval to accept a caller-supplied version string instead of always auto-incrementing the patch segment. **BREAKING**: `ResumeEditCandidateService::approve()` signature changes to accept the target version; both the existing web-form controller action and the new MCP tool call the updated signature.
- Update `README.md` and `CLAUDE.md` to document the new MCP tools and the version-on-approval behavior.

Rejecting a candidate is out of scope for this change — the existing web-form reject action (`/resume?revision=`) remains the only way to reject.

## Capabilities

### New Capabilities
- `resume-candidate-review-mcp-tools`: MCP tools, gated like `update-resume-section`, that let an authorized persona list pending resume edit candidates and approve one with a chosen version.

### Modified Capabilities
- `ai-persona-resume-editing`: The approval requirement changes so the approving caller supplies the new version number (validated, must exceed the base version) instead of the system always auto-incrementing the patch segment.

## Impact

- `app/Services/ResumeEditCandidateService.php` — `approve()` gains a required version parameter and validation; `nextPatchVersion()` becomes a suggested-default helper used by both the web controller and the new list tool, instead of the only path.
- `app/Http/Controllers/Admin/ResumeEditorController.php` — `approveCandidate()` reads/validates the submitted version from the web form.
- `resources/views/resume/index.blade.php` — Approve form gains a version input, pre-filled with the suggested default.
- New MCP tools under `app/Services/Mcp/Tools/ChatBot/ResumeEdit/` (e.g. `ListPendingResumeCandidatesTool`, `ApproveResumeCandidateTool`), extending the existing `AuthorizedResumeEditTool` base for the `edit-resume` permission gate.
- `README.md`, `CLAUDE.md` — documentation updates for the new tools and approval behavior.
