## Context

See proposal.md for why. Requirements are in `specs/ai-session-system-switching/spec.md`.

How the two sessions run a turn today:

```
  TargetedResumeService::continueConversation()      CompanyIntelService::runTurn()
    instructions = last `system` message              instructions = first `system` message
    history      = user/assistant text, in order      history      = user/assistant text, in order
    prompt       = last `user` message                prompt       = last `user` message
    agentFactory->forSystem($conversation->aiSystem)  agentFactory->forSystem($conversation->aiSystem)
```

- Both are stateless per turn: nothing is kept on the provider's side. A switch therefore only repoints `ai_conversations.ai_system_id`.
- Both read the saved `system` message, so keeping the prompt needs no work. The work is never rewriting that message.
- Intel already has a running state (`RoleIntel.status`), and its chat endpoint refuses during `running` and background `queued`. The resume chat has no running state. Its turn runs inside the `response()->stream()` closure in `ApplicationSessionController::chat()`.
- Session notes already exist (`TargetedResumeService::appendSessionNote()`: a `user`-role message with `metadata.origin`, rendered with a label through `ChatMessageBubble`'s `ORIGIN_NOTICES`). These notes **are** replayed to the model. The switch note is the first display-only origin.
- `ApplicationPayload::selectableSystems()` feeds the resume pickers. `StoreApplicationRequest` and `BeginApplicationAnalysisRequest` validate `ai_system_id` with `exists` only.
- Context data today: `context_length` is set on 2 of 9 chat systems. LM Studio systems carry `model_capabilities.max_context_length`, the model maximum (e.g. 262144), not the loaded size. Anthropic systems have neither.

## Goals / Non-Goals

**Goals:**
- One switch mechanism shared by both features. Only eligibility and the turn guard differ per feature.
- Resume eligibility defined once and used by the pickers, the start validation and the switch.

**Non-Goals:**
- Rewriting or rebuilding the prompt on a switch.
- Trimming history to fit a smaller context. The warning informs; it doesn't adapt.
- Per-turn overrides, or a switch control on public chat bots or the generic AI Conversations admin.
- Reading LM Studio's loaded-instance context. That needs code-talker (see Open Questions).

## Decisions

### 1. `TargetedResumeSystems` mirrors `CompanyIntelSystems`

Add `app/Services/TargetedResume/TargetedResumeSystems.php` (or beside `TargetedResumeService`, wherever siblings fit), with `eligible(): Collection` and `isEligible(AiSystem): bool`:

```
  ChatSystem::active()->where(fn ($q) =>
        $q->whereIn('system_prompt_id', [PROMPT_ID_TARGETED_RESUME, PROMPT_ID_COVER_LETTER])
          ->orWhereHas('featureDefaults', fn ($d) => $d->whereIn('feature', ['targeted-resume', 'cover-letter'])))
```

`ApplicationPayload::selectableSystems()` maps `eligible()` (same output shape). A shared contract (an interface both classes implement, say `SessionSystemEligibility` with `isEligible()` and `eligible()`) lets the switch service take either.

*Alternative:* a feature-keyed config map of allowed prompt ids. Rejected because intel's rule already exists as a class and its default can be any system. A class per feature keeps "default OR prompt" readable.

### 2. Validation on start uses a rule, not the controller

`StoreApplicationRequest` (when an `ai_system_id` is present) and `BeginApplicationAnalysisRequest` add a closure rule calling `TargetedResumeSystems::isEligible()`. The message is "That AI system isn't set up for targeted resumes." `exists` stays first, so a deleted id still gets its existing message. `AnalysisSystemGuard` is unchanged.

### 3. One switch service, feature passed in

`App\Services\AiSession\SessionSystemSwitcher`:

- `options(AiConversation, SessionSystemEligibility): array`. Returns the current system, the eligible others (`id`, `name`, `model`, `context` = `{tokens, source: configured|model_max|unknown}`), and `estimated_tokens`.
- `switch(AiConversation, AiSystem, SessionSystemEligibility): void`. Refuses ineligible (422) and same-system (422). In one transaction it updates `ai_system_id` and creates the note.

The turn guard is per feature, so the controllers check it before calling `switch()`. That keeps the service ignorant of `RoleIntel`.

Domain refusals become `ApplicationException` subclasses mapped in `ApplicationExceptionRenderer`: `SystemNotEligibleException` (422) and `SessionTurnInProgressException` (409), with a message per feature. Controllers don't catch them, matching the existing convention.

### 4. Endpoints

```
  GET  /api/admin/resume/applications/{application}/system-switch        -> options
  POST /api/admin/resume/applications/{application}/system-switch        {ai_system_id}
  GET  /api/admin/employment/company-intel/{roleIntel}/system-switch     -> options
  POST /api/admin/employment/company-intel/{roleIntel}/system-switch     {ai_system_id}
```

These sit in the existing `auth` + `can:edit-resume` groups, with a form request per POST. Options are fetched when the dialog opens rather than sent as page props, because the estimate grows with every turn. Both endpoints answer 409 when there is no session, like `chat` does.

### 5. Resume turn guard: an atomic cache lock

`ApplicationSessionController::chat()` acquires `Cache::lock("ai-conversation-turn:{id}", 600)` inside the stream closure and releases it in `finally`. The switch POST takes the same lock with `get()`. If it can't, it throws `SessionTurnInProgressException`. It holds the lock for the update and releases it afterwards. The chat side uses `block(5)`, so a switch committing at that instant delays the turn rather than failing it.

The cache driver is `file`, which implements atomic locks. The TTL bounds a lock orphaned by a killed PHP process. 600 s is above any realistic turn (intel's job timeout is 280 s, and resume turns are shorter).

*Alternatives:* a `turn_running` column, which is a migration plus a stale-flag cleanup problem. Client-only disabling would be bypassable and would ignore a second tab.

Intel's guard is `RoleIntel.status` in `running` or `queued`. It refuses `queued` even with `auto_start_pending`, because the page is about to stream it.

### 6. Display-only notes are filtered in both history builders

Use a `user`-role message with `metadata.origin = "system_switched"` and `metadata.from_ai_system_id`, `to_ai_system_id`, `from_name`, `to_name`. The content is human-readable ("Switched AI system from Sonnet to Bonsai for Jobs."), so the transcript and admin search read sensibly.

One constant lists the display-only origins (e.g. `AiConversationMessage::DISPLAY_ONLY_ORIGINS`, or a small support class if the package model can't take it). Both history builders drop those rows **before** choosing the last user message. Otherwise a note written after the admin's last message would become the turn's prompt. The size estimate uses the same filter.

*Why `user` role:* the admin performed the switch. `assistant` would also trip `ApplicationController::show()`'s "no assistant reply yet" auto-start check. `system` is out because the resume builder treats the **last** `system` message as the instructions.

The frontend adds `system_switched: "⇄ AI system switched"` to `ORIGIN_NOTICES`.

### 7. Estimate and context resolution

- **Estimate:** `ceil(chars / 4)` over the saved instructions plus the replayed messages. It's deliberately crude and computed server-side, so it measures exactly what the builders send. The recorded `input_tokens` is not used, because a tool loop sums it across steps and so overstates the history.
- **Context:** `context_length` → `configured`; else `model_capabilities.max_context_length` → `model_max`; else `unknown`.
- **Wording:** "likely to exceed" when estimate ≥ configured limit; "near the limit" at ≥ 80%. A `model_max` is shown only as a figure with a "model maximum; loaded size unknown" label.

### 8. Frontend

A shared `SwitchSystemControl` (current system name + Switch button → dialog with a system select, the warning and Confirm) in `resources/js/admin/components/`. It's used by `applications/ChatPanel.tsx` and `employment/company-intel/Show.tsx`.

- It's disabled while the page streams.
- It's hidden when the page's options say there is no other eligible system. A cheap `can_switch` boolean page prop avoids an empty control flashing.
- After confirming, `router.reload({ only: [...] })` refreshes the conversation and messages. `AISystemWarmupDetector` and the model-status URL then follow the new `ai_system_id`.
- It uses existing dialog components and styling: no gradients, no box shadows, no redesign.

## Risks / Trade-offs

- [The estimate is rough (chars/4)] → It's labelled "about", and the warning never blocks. The real failure still shows as the turn's error.
- [`model_max` overstates LM Studio's real loaded context] → It's never shown as "fits". The code-talker follow-up removes the gap.
- [The resume lock TTL could, after a crash, block switching for up to 10 min] → The refusal message says to retry shortly. A lock is only orphaned if PHP dies mid-stream.
- [Narrowing the start picker hides systems the owner may have used for resume sessions (4, 5, 10, 11, 12)] → Existing sessions keep working. Assigning prompt 4 or 5 to a system makes it eligible again. This is called out in the proposal.
- [A session started on a combined 4+5 system keeps its cover-letter guidance after moving to a 4-only system, and a session started on 4-only doesn't gain it] → This is intended ("the prompt belongs to the task") and covered by the spec's prompt-unchanged scenario.
- [Earlier tool results aren't visible to the new model] → That's already true of every turn. The warning states it.

## Migration Plan

No migrations or data changes. Deploy is the usual pull/build. Restart the queue worker so background intel turns use the updated history builder (developer-owned, per CLAUDE.md *Queues*). Rollback is a code revert. Notes already written stay as harmless labelled messages. The old builder would replay them to the model, which is acceptable.

## Open Questions

- **Loaded-instance context (code-talker, developer-owned):** `LmStudioServerClient::listModels()` drops LM Studio's per-instance loaded context. If code-talker exposes it, add it to the resolution order between `configured` and `model_max` as `loaded`. That's an additive change to decision 7 that doesn't alter the spec's ordering intent.
