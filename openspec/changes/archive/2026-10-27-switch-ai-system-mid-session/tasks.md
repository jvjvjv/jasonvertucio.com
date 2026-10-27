## 1. Eligibility

- [x] 1.1 Add `TargetedResumeSystems` (`eligible()`, `isEligible()`) and a shared eligibility contract implemented by it and `CompanyIntelSystems`. Verify with a feature test (DatabaseTransactions) that the targeted-resume or cover-letter default, prompt 4 and prompt 5 systems are eligible, and that an inactive system, a voice system and a prompt-51 system are not
- [x] 1.2 Point `ApplicationPayload::selectableSystems()` at `TargetedResumeSystems::eligible()`, keeping its output shape. Verify with a test asserting the New Session page's `systems` prop excludes a non-matching system
- [x] 1.3 Add the eligibility rule to `StoreApplicationRequest` (when `ai_system_id` is present) and `BeginApplicationAnalysisRequest`. Verify tests reject an ineligible id with a validation error on `ai_system_id` and create or attach nothing, while existing tests in `ApplicationCreateTest` and `ApplicationDiscussionTest` still pass

## 2. Display-only notes

- [x] 2.1 Define the display-only origins constant (`system_switched`) and filter those rows in `TargetedResumeService`'s history build and `CompanyIntelService::messagesFor()`, before choosing the last user message. Verify tests where a note follows the last user message: the prompt is still that user message and no history entry contains the note's text, for both features
- [x] 2.2 Add `system_switched: "⇄ AI system switched"` to `ORIGIN_NOTICES` in `ChatMessageBubble.tsx`. Verify by viewing a seeded note in the Discussion chat locally

## 3. Switch service and guards

- [x] 3.1 Add `SystemNotEligibleException` (422) and `SessionTurnInProgressException` (409) as `ApplicationException` subclasses and map them in `ApplicationExceptionRenderer`. Verify a test hitting each refusal gets the mapped status and message
- [x] 3.2 Add `SessionSystemSwitcher::switch()`: refuses ineligible and same-system, and in one transaction updates `ai_system_id` and writes the note with from/to ids and names. Verify tests: success repoints the system and writes exactly one note; each refusal leaves the system and transcript unchanged
- [x] 3.3 Add `SessionSystemSwitcher::options()`: the current system, the eligible others with `context {tokens, source}` resolved configured → model_max → unknown, and `estimated_tokens` (chars/4 over instructions + replayed messages, notes excluded). Verify tests for each context source and that a note's text doesn't change the estimate
- [x] 3.4 Wrap the resume chat stream in `Cache::lock("ai-conversation-turn:{id}", 600)` (acquire with `block(5)` inside the closure, release in `finally`). Verify a test that holds the lock and gets a 409 from the switch endpoint; existing chat tests still pass

## 4. Endpoints

- [x] 4.1 Add `GET|POST applications/{application}/system-switch` (form request for POST; 409 without a session; lock-guarded switch). Verify tests for options, a successful switch, ineligible 422, no-session 409, turn-in-progress 409 and 403 without `edit-resume`
- [x] 4.2 Add `GET|POST company-intel/{roleIntel}/system-switch`, refusing while Role intel is `queued` or `running`. Verify tests for options, a switch followed by a Retry that resolves the new system, 409 for running and queued, and 403 without `edit-resume`
- [x] 4.3 Send a `can_switch` boolean prop from `ApplicationController::show()` and `CompanyIntelController::show()`. Verify tests that it's false with no other eligible system or no session, and true otherwise

## 5. Frontend

- [x] 5.1 Build `SwitchSystemControl` in `resources/js/admin/components/`: current system, Switch button, a dialog that fetches options, a select, the size warning per the spec wording and the tool-results caveat, and Confirm. It's disabled while streaming and hidden when `can_switch` is false. Verify `npm run build` succeeds and the husky lint passes on the new files
- [x] 5.2 Mount it in `applications/ChatPanel.tsx` and `employment/company-intel/Show.tsx`, reloading the conversation and messages after a switch so the warm-up detector follows the new system. Verify locally (Docker `jv-app`, Vivaldi) that switching shows the note, the new current system, and that the next reply comes from the new model per the interaction log
- [x] 5.3 Do a frontend-design pass on the control and dialog (existing look, no gradients or box shadows), then take screenshots of the control, the dialog in its exceed, model-max and unknown states, and the note. Show them to the developer before merging

## 6. Docs and wrap-up

- [x] 6.1 Update CLAUDE.md *Application Tracking* and *Company Intel* with switching, the targeted-resume eligibility rule and display-only notes. Verify by reading the diff
- [x] 6.2 Run the affected tests (`php artisan test --compact` filtered to Application*, CompanyIntel and the new switch tests) and confirm they pass; ask the developer whether to run the full suite
- [x] 6.3 Record the code-talker follow-up (expose LM Studio loaded-instance context in `LmStudioServerClient::listModels()`) for the developer. Verify it's listed in the final hand-off message, with no edits made to code-talker
