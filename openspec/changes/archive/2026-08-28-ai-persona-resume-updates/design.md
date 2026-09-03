## Context

The main resume is fully relational today: `resume_versions` (id, `version` string with a **unique** constraint and `YYYY.MAJOR.MINOR` format enforced by `DatabaseResumeVersionService`, `is_current` boolean, docx/pdf paths) has `hasMany`/`hasOne` children (`ResumePersonalInfo`, `ResumeSkillCategory`+`ResumeSkill`, `ResumeExperience`(+bullets), `ResumeEducation`, `ResumeProject`(+bullets), `ResumeTechnicalProfileCategory`), all keyed on `version_id`. The admin Inertia editor writes to this shape directly.

A parallel, already-shipped pattern exists one level over: `targeted_resumes` stores a full resume-shaped payload as a single `tailored_data` JSON column (FK'd to a base `resume_version_id` and an `ai_conversation_id`), with a `status` string column, and the AI Persona already has a permission-gated mutating tool for it (`SaveTailoredResumeTool` / `AuthorizedResumeTool`, gated on Keystone's `save-resume` permission via `AuthorizedResumeTool::shouldRegister()`), invoked from the existing (public-facing) `ChatBotController` conversation. `targeted-resume-manual-editing` also establishes the convention of recording out-of-band edits as `AiConversationMessage` rows with `metadata.origin`.

This change needs a similar mutating-tool + draft/approve flow, but for the *main* resume rather than a job-targeted one, with the added requirement that consecutive persona edits within a rolling time window collapse into the same draft, and a new one starts after the window lapses.

## Goals / Non-Goals

**Goals:**
- Let an authorized AI Persona edit any part of the main resume (personal info, summary, skills, experience, education, projects, technical profile) through a tool call.
- Never mutate the live/`is_current` resume directly from a tool call — all persona edits land in a reviewable draft.
- Batch same-session-ish edits (within a rolling 12h window since the draft's last edit) into one revision; edits after the window start a new revision.
- Let a permitted human view any revision on the existing admin resume preview surface via a query parameter, and approve or reject it.
- While a candidate is pending for the live resume, block manual edits via the admin editor until that candidate is resolved (approved or rejected).
- Approving a revision produces a new live `resume_versions` row using the existing `YYYY.MAJOR.MINOR` versioning and regenerates DOCX/PDF via the existing `GeneratesResumeDocuments` trait — i.e. approval looks, to the rest of the system, just like an admin saving the editor form.

**Non-Goals:**
- Real-time collaborative merge between a pending AI draft and a concurrent human edit of the live resume (see Risks).
- A field-level diff/redline UI for review (out of scope for v1; the preview page rendering the full snapshot is sufficient).
- Multi-draft branching (more than one *open* draft lineage at a time) — one active draft chain per base version.
- Changing `resume_versions`' existing column shapes or its version-string format/regex.

## Decisions

### 1. Store drafts as a JSON snapshot table, not parallel relational rows

**Decision:** Add a new `resume_edit_candidates` table: `id`, `base_resume_version_id` (FK → `resume_versions`, the version it branched from), `revision_number` (unsigned int, 1-based per base version), `status` (`pending`|`approved`, default `pending` — there is no `rejected` status because rejecting a candidate deletes its row outright, see Decision 4), `snapshot` (JSON — the full resume shape: personal info, skills, experience, education, projects, technical profile), `ai_conversation_id` (nullable FK → `ai_conversations`), `batch_started_at`, `last_edited_at`, `approved_at`/`approved_by_user_id` (nullable), timestamps.

**Why:** `resume_versions.version` has a DB-level `unique` constraint and an app-level `YYYY.MAJOR.MINOR` regex; giving every in-progress draft its own real `resume_versions` row (as the original request's ".rc-N" version-string suffix implied) would require either relaxing that uniqueness/format (risking the live-version invariant `DatabaseResumeVersionService` relies on) or inventing collision-prone placeholder version strings. It would also mean inserting/updating full sets of child rows (`ResumeExperience`, bullets, etc.) on every single small tool call. A JSON snapshot column is exactly the pattern `targeted_resumes.tailored_data` already establishes in this codebase for "a whole resume's worth of data awaiting a human decision," and each persona edit becomes one cheap JSON read-modify-write instead of N relational statements.

**Alternative considered:** Extend `resume_versions` directly with `status`/`revision_number`/`base_version_id` columns and let candidates be ordinary (non-current) rows with real child tables. Rejected: forces a decision on the unique-version-string problem above, and versioning every micro-edit relationally is heavier than necessary for a draft that may never be approved.

### 2. Batching window: attach-or-branch on tool invocation, not a scheduled job

**Decision:** When the tool handles an edit request:
1. Resolve the current `is_current` `resume_versions` row (the base).
2. Look up the most recent `resume_edit_candidates` row for that base with `status = 'pending'`.
3. If one exists and `last_edited_at` is within the configured window (default 12h, `config('resume.ai_edit_batch_window_hours', 12)`) of "now," apply the edit to its `snapshot` in place and bump `last_edited_at`.
4. Otherwise (none exists, or the window elapsed), create a new `resume_edit_candidates` row with `revision_number` = previous max + 1 for that base, seeding `snapshot` from the *previous pending/approved candidate's snapshot* if one exists (so edits aren't lost), or from the live version's current data if this is the first-ever draft.

**Why:** No background scheduler is needed — the 12h rule is evaluated lazily at the moment of the next edit, matching how the requirement is actually used ("if 12 hours goes by ... more edits ... duplicate the latest rc"). This also naturally handles "approve, then edit again" — once a candidate is `approved`, step 2's lookup for `status = 'pending'` won't find it, so the next edit always branches from the (now-updated) live version.

**Note on multiple pending candidates:** a superseded candidate from step 4 is *not* auto-rejected — it keeps `status = 'pending'` (per the site owner's decision that a *human-initiated* rejection is always an explicit, individual action; see Decision 4). So more than one `pending` candidate can exist for the same base version at once: the latest revision (the one new persona edits attach to) plus any older abandoned revisions. The pending-manual-edit block (Decision 4a) is keyed on "any pending candidate exists for this base," not "exactly one."

A human resolves the pile-up in one of two ways: approve the one they want (which auto-rejects every other pending candidate for that base — see Decision 4's update below, added after the site owner flagged the data-clobbering risk this created), or reject the unwanted ones individually first.

### 3. Tool visibility is a dual gate: AiSystem allow-list AND user permission

**Decision:** The new tool(s) live under `app/Services/Mcp/Tools/ChatBot/ResumeEdit/`, extend an `AuthorizedResumeTool`-shaped base, and are visible for a given conversation turn only when **both** of the following hold:
1. The conversation's `AiSystem` explicitly includes the tool in its `allowed_tools` (the existing per-system tool allow-list mechanism, `config/code-talker.php` around `AiSystem::allowed_tools`) — i.e. an AiSystem must be deliberately configured to grant this capability at all.
2. The conversation's user holds the Keystone `edit-resume` permission — checked via `shouldRegister()`/`guard()` exactly like the targeted-resume tools.

They run in the same (existing, public-facing) `ChatBotController` conversation surface; there is no separate admin-only chat. Both gates are enforced independently — `shouldRegister()` failing (no `edit-resume`) hides the tool regardless of `allowed_tools`, and an `AiSystem` that omits the tool from `allowed_tools` never offers it regardless of the user's permission.

**Why:** A single permission check isn't enough — it only answers "may this *user* do this," not "should *this persona/system* be able to." Two independent operators (which AiSystem is configured with this capability, and which human is in the conversation) need to both say yes before a mutating resume tool is exposed. This also matches the existing `AiSystem::allowed_tools` mechanism already used to scope other tools per system, rather than inventing a new authorization concept.

### 4. Preview page: `version`/`revision` query param resolves to either a live version or a candidate

**Decision:** The existing admin resume preview page/route accepts an optional `revision` param (candidate id) in addition to today's default (current live version). When present, gate on `edit-resume`, load the `resume_edit_candidates` row, and render its `snapshot` in the same shape the page already renders live data — plus **Approve** and **Reject** actions when `status = 'pending'`.

**Approve** calls a new `ResumeEditCandidateService::approve(ResumeEditCandidate $candidate)` that:
- Creates a new `resume_versions` row (next version per existing bump semantics), `is_current = true` (unsetting the prior one, reusing `DatabaseResumeVersionService`'s existing unset-then-set logic).
- Writes the candidate's `snapshot` into the new version's child tables, reusing whatever materialization code `ResumeEditorController::update()` already uses for the admin form (extract to a shared method if it's currently inline).
- Regenerates DOCX/PDF via `GeneratesResumeDocuments`.
- Marks the candidate `approved`, stamps `approved_at`/`approved_by_user_id`.
- **Permanently rejects (deletes) every other `pending` candidate sharing the same `base_resume_version_id`.** Added after the site owner flagged the failure mode this closes: two pending candidates can share a base version (per the "multiple pending candidates" note above); the next-version-number scheme bumps the *base's* patch by one regardless of which candidate is approved, so approving a second, stale sibling later would compute the same version string, find the row `firstOrCreate()` already made, and silently overwrite the first approval's data with stale content. Auto-rejecting siblings on approval closes this off entirely rather than requiring a human to remember to clean them up first.

**Reject** permanently **deletes** the candidate row (not a soft `discarded` status) without touching the live version; the next persona edit will branch a fresh candidate from the (still-)live version per Decision 2. There is no un-reject — this is a destructive action, matching how the site owner described it ("reject will just outright delete the version").

### 4a. Manual (human) resume edits are blocked while a candidate is pending

**Decision:** The admin resume editor (`ResumeEditorController::update()`, and its Inertia form) SHALL refuse to save changes to a resume version that has a `pending` `resume_edit_candidates` row. The editor UI SHALL indicate that an AI-drafted revision is awaiting review and link to the preview page for it, instead of allowing the human to edit around it.

**Why:** The site owner decided concurrent human + AI edits are disallowed outright rather than merged or warned-about after the fact (this replaces the "warn on stale base version" idea from an earlier draft of this design for the *manual-edit* side — the preview-page staleness warning in Decision 4 still applies to the *approval* side, e.g. if something else changed `is_current` between candidate creation and approval). A human resolves the conflict by approving or rejecting the pending candidate first; only then can they edit the (now up to date) live resume directly again.

### 5. DOCX/PDF are not regenerated per persona edit

**Decision:** Document generation only happens on Approve, not on every tool call.

**Why:** `GeneratesResumeDocuments` shells out to a Node.js script; running it on every small AI edit (which may happen many times per minute in a chat) is wasteful and slow. The preview page renders directly from the JSON snapshot, so no DOCX/PDF is needed pre-approval.

### 6. Audit trail via `AiConversationMessage`

**Decision:** Every successful edit tool call appends a `user`-role `AiConversationMessage` with `metadata.origin = 'ai_resume_edit'` summarizing what changed, following `targeted-resume-manual-editing`'s convention, without triggering a synchronous extra agent turn.

## Risks / Trade-offs

- **[Risk] A human wants to edit the live resume while a candidate is pending** → blocked outright per Decision 4a, which is a deliberate trade-off: it trades "always able to edit" for "no silent overwrite," at the cost of the human being unable to fix an urgent typo without first approving or rejecting whatever the AI has drafted. **Mitigation:** the editor's blocking message links straight to the pending candidate's preview page so resolving it (approve or reject) is one click away.
- **[Risk] Rejection is a hard delete with no undo** — if a candidate is rejected by mistake, its edits are gone (the conversation transcript still has the `ai_resume_edit` messages describing what changed, but not a restorable record). **Mitigation:** none planned for v1 beyond the conversation-transcript trail; call this out clearly in the reject confirmation UI ("this cannot be undone"). Note this now also applies to a *sibling's* pending candidates auto-rejected as a side effect of approving one of them (see Decision 4) — same no-undo caveat.
- **[Resolved] Approving a stale sibling candidate could silently overwrite a just-approved version** — two candidates sharing a base version both compute the same next-patch version string on approval; approving the second after the first would `firstOrCreate()` the same row and overwrite its data. **Fix:** approval now auto-rejects every other `pending` candidate for the same base version in the same transaction (Decision 4), so a stale sibling can never reach Approve at all.
- **[Risk] Unbounded pending-candidate accumulation if nobody reviews them** — since there's no auto-expiry and rejection is manual/individual (per the site owner's decision), a base version could sit blocked from manual edits indefinitely if the pending candidate is never resolved. **Mitigation:** none beyond UI visibility (the editor's blocking banner and a link to review) — this is accepted as the intended behavior, not a bug to engineer around.
- **[Risk] Partial-failure mid-materialization on Approve** (e.g. DOCX generation throws after relational rows are written) → leaves a live version with stale documents. **Mitigation:** wrap the relational writes in a DB transaction; treat DOCX/PDF regeneration failure the same way `targeted-resume-manual-editing` does for its regen failure (persist the data, surface the generation error, don't roll back the approval).
- **[Trade-off] JSON snapshot means the candidate's data shape must be kept in lockstep with the live relational schema** — any future field added to `ResumeExperience` etc. needs a corresponding key in the snapshot shape used by both the tool's schema and the materialization code. Mitigated by centralizing the shape in one contract (e.g. a form-request-like data object) used by both the admin editor's save path and the new materialization service.

## Migration Plan

1. Add Keystone permission `edit-resume` if it does not already cover this (research shows `edit-resume` already exists and gates the admin editor — reuse it; do not introduce a new permission).
2. Migration: `create_resume_edit_candidates_table` (columns per Decision 1), FK `base_resume_version_id` → `resume_versions` (`restrictOnDelete`), FK `ai_conversation_id` → `ai_conversations` (`nullOnDelete`), FK `approved_by_user_id` → `users` (`nullOnDelete`).
3. Add `ResumeEditCandidate` model + factory.
4. Extract/share the admin editor's "write payload into relational child rows for a version" logic into a reusable service method (used by both `ResumeEditorController::update()` and the new approval path) — only if it isn't already isolated.
5. Add the new MCP tool(s) + registration wiring (mirroring `AuthorizedResumeTool`/`TargetedResumeToolRegistry`, or plugging into however `ChatBot/*` tools are currently discovered).
6. Extend the preview route/page for `?revision=` + Approve/Reject actions.
7. Add the pending-candidate guard to `ResumeEditorController::update()` (and its form UI) so manual edits are refused while a candidate is pending for that base version.
8. No changes to existing `resume_versions` rows or schema; fully additive. Rollback = drop `resume_edit_candidates` table and remove the tool registration; no impact on existing live resume data.

## Open Questions

- Resolved: gating is a dual check — the conversation's `AiSystem` must include the tool in `allowed_tools`, and the user must hold `edit-resume`. `edit-resume` is reused as-is (no new permission).
- Resolved: rejecting a candidate hard-deletes its row; there is no `discarded` status and no undo.
- Resolved: manual admin edits are blocked outright while any candidate is `pending` for that base version; there is no merge or override.
- Resolved: abandoned/stale pending candidates are never auto-rejected — a human must reject them individually.
- Where exactly does `ResumeEditorController::update()`'s payload-to-relational-rows logic currently live, and is it already extractable, or does it need a refactor first (bumping this change's scope)?
- Does the 12h batching window need to be configurable per-persona/per-conversation, or is a single global config value sufficient for v1?
