## Why

The AI Persona already has authorized, permission-gated write access to *targeted* resumes via `SaveTailoredResumeTool` and the `targeted_resumes` table, but no equivalent path exists for editing the *main* resume (`resume_versions` + its child tables: personal info, skills, experience, education, projects, technical profile). Today the only way to change the main resume is the Inertia admin editor form. Giving the persona a tool to propose edits — with a safe draft/review/approve workflow instead of writing directly to the live `is_current` version — lets the site owner iterate on their resume conversationally while keeping a human approval gate before anything goes live.

## What Changes

- Add a mutating MCP tool (or small tool family) the AI Persona can call to edit personal info, summary, skills, experience, education, projects, and technical profile — mirroring the `SaveTailoredResumeTool` pattern (`guard()`, `schema()`, `handle()`, `AuthorizedResumeTool` base). The tool is visible only when **both** the conversation's `AiSystem` explicitly allows it (`allowed_tools`) and the conversation's user holds the `edit-resume` Keystone permission (`AuthorizedResumeTool::shouldRegister()`).
- On the first persona-initiated edit, snapshot the current active resume into a new **candidate** record rather than writing directly to the live version. Store the candidate as a JSON snapshot (mirroring `targeted_resumes.tailored_data`) plus a `revision_number` and the base `resume_version_id` it was branched from.
- Group consecutive persona edits into the same candidate/revision as long as they land within a rolling time window (12 hours, configurable) of the candidate's last edit. Once that window elapses, the next persona edit starts a new revision (`revision_number` + 1) branched from the latest candidate, not the original active version.
- Extend the admin resume preview page to accept a `version` (or `revision`) query parameter that loads a specific candidate's full snapshot (all sub-entities) instead of the live active version, gated by the same read permission used elsewhere (`edit-resume`/`read-resume`).
- Add an **Approve** action on that preview page (visible only to a user with `edit-resume`) that materializes the candidate's snapshot into a new live `resume_versions` row (bumping `version` per the existing `YYYY.MAJOR.MINOR` scheme), flips `is_current`, regenerates DOCX/PDF via the existing `GeneratesResumeDocuments` trait, and marks the candidate `approved`.
- Add a **Reject** action for a candidate that permanently deletes it (no soft "discarded" state, no undo) so an unwanted revision doesn't linger as the implicit "latest draft" for future persona edits.
- While any candidate is `pending` for the live resume, block manual edits via the admin resume editor until that candidate is approved or rejected — no concurrent human/AI editing of the same base version.
- Record every persona edit as an `AiConversationMessage` on the conversation (role `user`/system, `metadata.origin = "ai_resume_edit"`), following the `targeted-resume-manual-editing` precedent, so the transcript stays a complete audit trail and the agent has edit context on its next turn.
- New migration(s) for the candidate/revision table (does not alter `resume_versions`'s existing columns or `YYYY.MAJOR.MINOR` version format).

## Capabilities

### New Capabilities
- `ai-persona-resume-editing`: Lets an authorized AI Persona propose edits to the main resume through a mutating tool, batches those edits into time-windowed draft revisions instead of writing live, blocks manual editing of the live resume while a revision is pending, and lets a permitted human review a specific revision on the preview page and approve or permanently reject it.

### Modified Capabilities
- (none — no existing spec's requirements change; this is additive alongside `targeted-resume-manual-editing`, which covers a different table/workflow)

## Impact

- **New DB table(s)**: a resume revision/candidate table with FK to `resume_versions` (base), JSON snapshot column(s), `revision_number`, `status` (`pending`/`approved` — rejection deletes the row instead of a third status), `batch_started_at`/`last_edited_at`, `approved_by`/`approved_at`.
- **New MCP tool(s)**: under `app/Services/Mcp/Tools/ChatBot/` (or a new `ResumeEdit` subfolder), extending the `AuthorizedResumeTool`-style base, gated on both the `AiSystem`'s `allowed_tools` and the user's `edit-resume` permission.
- **Modified**: admin resume preview route/page (Inertia) to accept `?version=`/`?revision=` and render Approve/Reject actions; the admin resume editor to block saves while a candidate is pending for that base version; `DatabaseResumeVersionService` or a sibling service to handle materializing an approved candidate into a new `resume_versions` row + child rows.
- **Reused, unchanged**: Keystone permissions (`edit-resume`, `save-resume`, `manage-ai-tools`), `GeneratesResumeDocuments` trait, `AiConversationMessage` audit pattern, existing `resume_versions.version` format/regex.
- **Resolved during review**: chat surface is the existing public `ChatBotController`, gated by the dual `allowed_tools` + `edit-resume` check; DOCX/PDF regenerate only on approval, never per-edit; concurrent human edits are blocked outright (not warned-about) while a candidate is pending; rejection is a hard delete with no undo; stale/abandoned candidates are never auto-rejected and must be rejected individually.
