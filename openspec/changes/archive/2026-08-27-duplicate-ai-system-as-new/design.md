## Context

`AiSystem` (model + admin CRUD live in the `jvjvjv/code-talker` package, consumed by this app's `AiSystemController`) has no versioning, draft state, or audit trail today. The Edit page (`resources/js/admin/pages/ai/systems/Edit.tsx`) always renders the form with `isEdit=true`, which `ProviderModelSelector.tsx` uses to permanently disable Provider, Model, and API Key — correct for a system that was configured once via Create and should stay stable afterward, since those fields are load-bearing for encrypted credentials and provider-specific capability metadata.

`AiSystemController::duplicate()` is the one path that creates a fresh, never-truly-configured record and routes it straight to that locked Edit form. `AiSystemController::update()` reinforces the lock server-side by silently discarding any submitted `provider`/`model` and reusing the existing DB values (`AiSystemController.php:122-132`), and `UpdateAiSystemRequest` has no validation rules for those fields at all. A genuinely new system never hits this path — `store()` redirects to the Index list, not Edit — so the lock has never had to account for a "new but landed on Edit anyway" state until now.

## Goals / Non-Goals

**Goals:**
- A duplicated AiSystem's first visit to Edit behaves like Create for Provider, Model, and API Key: editable, validated the same way `StoreAiSystemRequest` validates them.
- After the first successful `update()` on a duplicated system, it locks exactly like any other established system, permanently.
- The mechanism generalizes cleanly if `AiChatBot` (or another model) grows duplicate support later, without requiring a rewrite.
- The package (`AiSystemManager::duplicate()`) and the app stay behaviorally consistent.

**Non-Goals:**
- No general-purpose versioning/draft/audit-trail system for `AiSystem`. This is scoped to unlocking three fields for one edit window, not building history tracking.
- No change to `AiChatBot` — it has no duplicate feature today, so nothing here touches it.
- No change to how `system_prompt_id` is shared between the original and its clone. Prompts are a reusable library resource elsewhere in the app; forking the prompt row on duplication is a separate concern with its own trade-offs and is left for a future change if needed.
- No retroactive backfill of "pending" state onto already-duplicated systems created before this change ships — they keep whatever locked/unlocked state they currently have.

## Decisions

**Represent "pending first edit" as an explicit nullable `duplicated_at` timestamp column on `ai_systems`, set on duplication and cleared on first `update()`.**
- Alternatives considered:
  - *Infer from `created_at == updated_at`*: rejected — a genuinely new system created via `store()` and never touched again would also satisfy this the first time someone eventually opens its Edit page, incorrectly unlocking Provider/Model/API Key for an established system that was simply never edited yet. The signal needs to be specific to "created via duplication," not "never edited."
  - *Boolean flag (`is_pending_first_edit`)*: functionally equivalent to a nullable timestamp but discards useful info. A timestamp lets `duplicate()` also expose "duplicated N minutes ago" if ever useful, and `NULL` reads unambiguously as "not a pending duplicate" without a second column. Going with the timestamp.
  - *Full version/audit table*: overkill for a single boolean-shaped need; explicitly a non-goal.
- The column lives on the package's `AiSystem` migration (`code-talker`) since the model and its schema are package-owned; the app consumes it through the existing `AiSystem`/`AiSystemController`.

**Gate both the UI lock and server-side validation/persistence off the same `duplicated_at !== null` condition, computed once per request.**
- `AiSystemController::edit()` passes `pendingFirstEdit: $aiSystem->duplicated_at !== null` as an Inertia prop.
- `ProviderModelSelector.tsx` changes its disable condition from `disabled={isEdit}` to `disabled={isEdit && !pendingFirstEdit}` for Provider, Model, and API Key, and swaps the "cannot be changed" helper text for guidance matching Create's copy while pending.
- `UpdateAiSystemRequest` conditionally applies `StoreAiSystemRequest`'s provider/model/API-key rules only when the target `AiSystem` currently has `duplicated_at !== null` (checked via route-model-bound `$this->route('aiSystem')`), otherwise those keys are excluded from validation exactly as today.
- `AiSystemController::update()` only accepts submitted `provider`/`model`/`api_key` when `$aiSystem->duplicated_at !== null`; otherwise it keeps today's behavior of discarding them in favor of the existing DB values. On any successful save, it sets `duplicated_at = null` unconditionally — the first edit, whether or not the pending fields were actually changed, closes the window.

**Reuse `StoreAiSystemRequest`'s provider/model/API-key validation rules rather than duplicating them in `UpdateAiSystemRequest`.**
- Avoids the two request classes drifting out of sync (the current doc-comments already flag that `UpdateAiSystemRequest` "mirrors" Store's validation independently — a known smell this change should not deepen).

**Bring `AiSystemManager::duplicate()` (package, currently unused by the app) in line with the same `duplicated_at` semantics.**
- It's public package API; leaving it setting no flag while the app-facing controller does would make the package inconsistent for any other consumer. Update it to set `duplicated_at` the same way `AiSystemController::duplicate()` does, ideally by having the controller delegate to it instead of maintaining a second `replicate()` call — reduces the duplicate-duplication-logic problem noted in research.

## Risks / Trade-offs

- **[Risk]** A duplicated system that's never edited stays permanently "pending," so Provider/Model/API Key would still unlock if someone opens its Edit page far in the future. → **Mitigation**: this is the intended behavior — "pending" means "never actually configured," regardless of elapsed time — and the duplicate flow already redirects straight to Edit with a flash prompt to "update the name and settings as needed," so an unedited duplicate sitting around is an edge case, not the common path.
- **[Risk]** Package (`code-talker`) and app version skew: the app's `vendor/jvjvjv/code-talker` is installed via Packagist (`^0.12.1`), not a path repo, so a migration/model change made in the package source repo won't reach the running app until a new version is tagged and `composer update` run. → **Mitigation**: mirror the migration and model change into the app's `vendor/` copy for immediate local testing (as done for the prior LM Studio error-detail fix), and call out the release step explicitly in tasks.md.
- **[Risk]** Unlocking API key editing on the same Edit form as normal edits (once `pendingFirstEdit` is true) could accidentally overwrite the stored encrypted key with a blank value if the frontend doesn't handle "field left untouched" the same way Create does. → **Mitigation**: reuse Create's existing API-key-field component/behavior verbatim rather than writing new logic for this state.

## Migration Plan

1. Add `duplicated_at` (nullable timestamp) to `ai_systems` via a package migration; mirror it into the app's local `wink`/`jasonvertucio` databases per this repo's dual-database convention.
2. Update `AiSystemManager::duplicate()` and `AiSystemController::duplicate()` to set `duplicated_at = now()` on the clone.
3. Update `UpdateAiSystemRequest` and `AiSystemController::update()` to branch on `duplicated_at !== null` and clear it on save.
4. Update `Edit.tsx` / `ProviderModelSelector.tsx` to accept and act on the `pendingFirstEdit` prop.
5. Mirror package changes into `vendor/jvjvjv/code-talker` locally; tag/release the package version bump as a follow-up so `composer.json` can be updated durably.

Rollback: drop the `duplicated_at` column and revert the controller/request/frontend changes; no data migration is needed since the column has no downstream dependents outside this feature.

## Open Questions

- Should `duplicated_at` (or an equivalent) also be surfaced in the AiSystem Index list (e.g., a "needs setup" badge) so admins can spot un-configured duplicates at a glance? Left for a follow-up if the developer wants it — not required for the core bug fix.
