## Why

An Application or Company Intel session is bound to the AI system it was started on. There is no way to move it to a different model, so an admin who wants to retry a session on another model starts over. Each turn already re-sends the saved prompt and the whole transcript, so moving a session to another system costs no extra mechanism. What needs building is the guardrails: which systems qualify, a warning about context size, and a record in the transcript.

Systems are currently offered for resume sessions too loosely. `ApplicationPayload::selectableSystems()` accepts any active chat system that has *any* prompt or *any* feature default. That is how a system built for an unrelated job (prompt 51) can start a targeted-resume session today.

## What Changes

- An admin can **permanently switch** the AI system of an Application session (Discussion page chat) or a Company Intel session (intel page chat). The conversation's `ai_system_id` is repointed. Every later turn, background intel turns and Retry included, runs on the new system.
- **Only systems whose prompt matches the feature are offered**:
  - Targeted resume: an active chat system that is the `targeted-resume` or `cover-letter` default, or whose `system_prompt_id` is 4 (Targeted Resume) or 5 (Cover Letter).
  - Company intel: the existing `CompanyIntelSystems` rule (the `company-intel` default, or prompt 6).
- **The same targeted-resume rule now governs starting a session** (Begin Analysis, New Session's "Begin Analysis" and "I Applied" with a system). The server rejects an ineligible system instead of only checking that it exists. **BREAKING (admin only):** systems without a matching prompt (today 11, 4, 5, 10, 12) are no longer offered for new resume sessions. Existing sessions on them keep working and can switch to an eligible system.
- **The session keeps its saved prompt.** Switching never rewrites the `system` message. Because only matching systems qualify, the kept prompt fits the new system.
- **A display-only transcript note** (`metadata.origin = "system_switched"`) records the switch with the from and to systems. It is shown in the chat and never sent to the model.
- **A switch is refused while a turn is running** (intel: Role intel `queued` or `running`; resume: a turn currently streaming).
- **A size warning** before confirming: the estimated size of what the next turn will send, compared against the best-known context limit (the system's `context_length`, else the model maximum LM Studio reports, labelled as a maximum, else "not set"). The warning informs; it never blocks.
- The switch control is hidden when no other system is eligible.

## Capabilities

### New Capabilities
- `ai-session-system-switching`: switching an Application or Company Intel session to another AI system: eligibility per feature (including which systems may start a targeted-resume session), the kept prompt, the display-only note, the refusal during a turn, and the size warning.

### Modified Capabilities
<!-- None. The application-tracking and company-intel requirements are unchanged in wording; the new capability narrows which systems their pickers offer. -->

## Impact

- **Backend**: new `TargetedResumeSystems` (eligibility, mirroring `CompanyIntelSystems`); a shared switch service; switch endpoints under the existing application and intel API groups; eligibility validation on `StoreApplicationRequest` and `BeginApplicationAnalysisRequest`; history builders in `TargetedResumeService` and `CompanyIntelService` skip display-only notes; a turn lock around the resume chat stream; two new `ApplicationException` subclasses mapped in `ApplicationExceptionRenderer`.
- **Frontend**: a switch control and confirmation dialog on `ChatPanel` (Discussion page) and the intel `Show` page; a new `system_switched` entry in `ChatMessageBubble`'s `ORIGIN_NOTICES`; the New Session and Begin Analysis pickers get the narrower list from `selectableSystems()`.
- **Data**: no migrations. A switch is an `ai_system_id` update plus one `ai_conversation_messages` row.
- **Docs**: CLAUDE.md (*Application Tracking* and *Company Intel*) describes switching and the eligibility rule.
- **Follow-up outside this repo (developer-owned)**: code-talker's `LmStudioServerClient::listModels()` drops LM Studio's loaded-instance context length. Exposing it would let the size warning compare against the context the model is actually loaded with, not its maximum. This change works without it.
