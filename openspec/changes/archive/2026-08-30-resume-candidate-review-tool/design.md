## Context

`ResumeEditCandidateService::approve()` (`app/Services/ResumeEditCandidateService.php:80`) always computes the next version by bumping the patch segment of the candidate's base version (`nextPatchVersion()`). There is no way for a reviewer — human or persona — to ship a candidate as a major or minor release. Separately, the AI-persona resume-edit MCP tools (`app/Services/Mcp/Tools/ChatBot/ResumeEdit/`) can draft edits (`update-resume-section`) and see that *a* revision is pending (`get-resume-data`'s `pending_revision_number`), but there's no tool that lists every pending candidate with enough detail to review, and no tool that approves one — approval is only reachable through the web form on `/resume?revision=`.

## Goals / Non-Goals

**Goals:**
- Let the approving caller — the web form or a new MCP tool — pick the version a candidate is published as, with the current auto-patch-bump value offered as a suggested default so the common case still needs no extra input.
- Add MCP tools, gated exactly like `update-resume-section`, so an authorized persona can list pending candidates for the live resume version and approve one by revision number and version, without leaving the chat interface.

**Non-Goals:**
- Changing how candidates are created, batched, or how the batching window works (`ai-persona-resume-editing` batching requirements are untouched).
- A reject MCP tool — rejection stays web-form-only for this change; only listing and approving move into MCP tools.
- Listing candidates across every historical base version — like the existing `get-resume-data`/`LoadsResumeDataWithRevisionInfo` tooling, the new list tool scopes to the current live resume version, which is the only base a persona can act against anyway.

## Decisions

- **Version supplied as a required parameter on both approval paths, not a separate confirmation step.** The web form's Approve button gains a pre-filled text input; the new `approve-resume-candidate` MCP tool takes `version` as a required schema field the persona must pass (echoing the suggested default returned by the list tool, or a caller-chosen higher major/minor version).
- **Validation lives in `ResumeEditCandidateService`, not in each caller.** `approve()` takes the target version as a required parameter and throws (mirroring the existing `RuntimeException` for a non-pending candidate) if it doesn't match `YYYY.MAJOR.MINOR` or isn't strictly greater than the base version. Both the controller and the MCP tool catch/translate this failure into their own error-response shape, but neither can bypass it.
- **"Strictly greater than base version" uses tuple comparison**, not string comparison (`2026.2.0` must sort after `2026.10.0` correctly) — compare `[year, major, minor]` component-wise, same shape `nextPatchVersion()` already parses.
- **New MCP tools extend `AuthorizedResumeEditTool`**, the same base class `UpdateResumeSectionTool` uses, so they get the same two-gate authorization (AiSystem `allowed_tools` filtering upstream in `ChatBotToolRegistry`, plus the `edit-resume` permission check in `guard()`/`shouldRegister()`) for free, rather than reimplementing authorization.
- **The list tool identifies candidates by `revision_number`, not database ID**, matching every other resume-edit tool's public surface (`get-resume-data`'s `revision_number` param, `update-resume-section`'s implicit revision targeting) — a persona never needs to know or expose the underlying primary key.
- **Approval via the MCP tool records a tagged conversation message**, mirroring `UpdateResumeSectionTool::recordEditMessage()`, so an approval performed via chat is visible in the conversation transcript the same way a persona-initiated edit is.

## Risks / Trade-offs

- [Persona (or its human) supplies an invalid or lower version and doesn't notice until the call fails] → The list tool returns a `suggested_version` value computed the same way the web form's default is, and the service-level validation error message states the required format/comparison explicitly so a retry can succeed.
- [A web-form approval and an MCP-tool approval race for the same candidate] → Unchanged from today: `ResumeEditCandidateService::approve()` already runs inside `DB::transaction()` and starts by checking `status !== 'pending'`, so the second caller to reach the transaction fails cleanly rather than double-approving.
- **BREAKING**: `ResumeEditCandidateService::approve()` gains a required `$version` parameter — a repo-wide grep confirms `ResumeEditorController::approveCandidate()` is the only existing caller; the new MCP tool becomes a second caller in the same change, so both are updated together.

## Migration Plan

No data migration needed — this only changes a method signature and adds new MCP tools (auto-discovered the same way existing `ChatBot` tools are). Deploy is a single release: update the service, the web controller, and add the new tools together; no feature flag needed since every existing caller is updated in the same change and new tools are inert until an `AiSystem`'s `allowed_tools` opts into them.
