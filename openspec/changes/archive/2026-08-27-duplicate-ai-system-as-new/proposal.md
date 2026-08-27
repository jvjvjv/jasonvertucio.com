## Why

Duplicating an AiSystem is the app's only way to change an existing system's provider, model, or API key — the "Duplicate" button's own help text says the API key "can only be changed by duplicating this system." But the duplicate action redirects straight to the Edit page, and the Edit page locks the Provider, Model, and API Key fields on every visit (`isEdit` is always `true` there), because those fields are meant to be locked only for systems that have already been configured once. A duplicated system has never actually been configured — it's carrying the original's provider/model/API key as a starting point, not a committed choice — so the very fields duplication exists to let the user change are the ones it immediately locks.

## What Changes

- Track whether an AiSystem has been through a real, deliberate edit yet, distinct from merely being created (via duplication or otherwise).
- A freshly duplicated AiSystem's first visit to the Edit page unlocks Provider, Model, and API Key (and their related validation) the same way the Create page does, instead of applying the "already configured" lock.
- The first time a duplicated system is saved through Update, it becomes a normal, fully-locked AiSystem from then on — provider/model/API key immutable again, same as any other edited system.
- Update `UpdateAiSystemRequest` validation and `AiSystemController::update()` to accept provider/model/API key changes only while a system is still in this pending-first-edit state, and to clear that state on save.
- Align the unused `AiSystemManager::duplicate()` in the `jvjvjv/code-talker` package with the same "pending first edit" flag so any other consumer of the package gets consistent behavior, even though the app doesn't currently call it.

## Capabilities

### New Capabilities
- `ai-system-duplication`: Defines how a duplicated AiSystem is created, how its "pending first edit" state is represented and surfaced to the Edit page, and how that state is cleared on first save.

### Modified Capabilities
(none — no existing spec currently documents AiSystem admin behavior)

## Impact

- **Backend**: `app/Http/Controllers/Admin/AiSystemController.php` (`duplicate()`, `update()`), `app/Http/Requests/UpdateAiSystemRequest.php`, a new migration adding a "pending first edit" marker to the `ai_systems` table (or equivalent field on the package model), `/home/jasonv/Code/@jvjvjv/code-talker/src/Services/Management/AiSystemManager.php` (`duplicate()`), `/home/jasonv/Code/@jvjvjv/code-talker/src/Models/AiSystem.php`.
- **Frontend**: `resources/js/admin/pages/ai/systems/Edit.tsx`, `resources/js/admin/pages/ai/systems/ProviderModelSelector.tsx` (the `isEdit`-driven disabling of Provider/Model/API Key needs a third state, not just create-vs-edit).
- **Out of scope**: `AiChatBot` has no duplicate functionality today and shares no code path with `AiSystem`'s duplication, so it is unaffected by this change.
