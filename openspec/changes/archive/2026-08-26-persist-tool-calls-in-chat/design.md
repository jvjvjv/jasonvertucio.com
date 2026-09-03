## Context

`jvjvjv/code-talker`'s `TurnRecorder::recordCompletedTurn()` already writes `tool_calls`/`tool_results` (JSON) onto the assistant's `AiConversationMessage` row whenever a turn used tools, alongside the existing `blocks` column (ordered `{type: 'text'|'reasoning', content}` entries built by `ResponseBlocks`). Live tool activity streams to the browser today as `tool_use_progress` SSE frames (`ConversationTurnRunner.php:150-221`), consumed by `useChatStream.ts` into ephemeral `streamingToolPanels` state and rendered by `ToolsPanel`/`ToolPanel` (`resources/js/components/ToolsPanel.tsx`). That state is thrown away the moment a turn finishes (`useChatStream.ts` clears it at turn start/end), and `Jvjvjv\CodeTalker\Services\ChatBot\ChatBotPresenter::transcript()` — what actually builds the page's `messages` prop — never selects `tool_calls`/`tool_results` at all. Net result: tool activity is visible only for the duration of the live stream and never again.

A security-relevant constraint governs how much of this can ever reach the browser: `ChatBotController::message()` only calls `usingToolPayloads()` — which is what puts `input`/`output` into the live `tool_use_progress` frames — outside `production`, because tool arguments/results "can carry whatever the model or a fetched page put in them — including a credential the model is handling on the visitor's behalf" (`app/Http/Controllers/ChatBotController.php:159-162`). Any historical-transcript equivalent must honor the same gate.

One real data gap exists that this change does **not** close: `Streaming\Events\ToolResult::$successful`/`$error` are never written into the `tool_results` column — only `Data\ToolResult::toArray()` (`id`, `name`, `arguments`, `result`, `result_id`) is persisted (`TurnRecorder.php:56`, sourced from `ConversationTurnRunner.php:151`). Fixing that would require changing `ConversationTurnRunner`/`TurnRecorder` in code-talker itself, which is out of scope per the proposal's explicit goal of a host-only change; see Non-Goals and Open Questions.

## Goals / Non-Goals

**Goals:**
- A message's tool calls/results, once persisted by `TurnRecorder`, are visible in the chat UI for the lifetime of the conversation — after the turn ends, after a reload, and when reopening an older conversation.
- The same visual tool-panel treatment (`ToolsPanel`) is reused for both live and historical tool activity, rather than inventing a second UI.
- Production's existing input/output redaction is preserved exactly for historical data — nothing this change adds is allowed to expose more than the live stream already does.
- No changes to `jvjvjv/code-talker`.

**Non-Goals:**
- Persisting or displaying a success/failure badge on historical tool results — not stored today (see Context); left for a follow-up that would need a small `code-talker` change.
- Making a tool-only turn (no text/reasoning at all) persist — `TurnRecorder` only creates a message row when `text !== '' || reasoning !== ''` (`TurnRecorder.php:39`); a turn with zero text/reasoning output produces no row at all today, tool data included. Rare (the agent loop generally continues to a final answer) and out of scope for a host-only fix.
- Reordering tool activity to interleave exactly with text/reasoning blocks by timestamp — `blocks` and `tool_calls`/`tool_results` are stored as separate, unordered-relative-to-each-other arrays; this change renders persisted tool activity as a single group per message (matching how `streamingToolPanels` already renders per turn today), not interleaved mid-text.

## Decisions

### Read tool data via a host-side transcript query, not `ChatBotPresenter::transcript()`
`HostChatBotPagePayload` will build its own transcript (or wrap the package one and enrich each row) by querying `AiConversationMessage` directly for `tool_calls`/`tool_results` alongside what `ChatBotPresenter::transcript()` already returns. This is a read of already-public model columns — no package API is missing, so there's nothing to add to code-talker.

**Alternative considered**: ask `ChatBotPresenter::transcript()` to include the columns itself. Rejected as a *package* change (touches code-talker) when the host can already read the same Eloquent model directly; also `ChatBotPresenter` is intentionally minimal/generic and other hosts may not want tool payloads in their transcript by default.

### Redact `arguments`/`result` outside non-production, mirroring `ChatBotController::message()`
When the host transcript builder serializes a message's `tool_calls`/`tool_results` for the page payload, it strips `arguments` (from tool calls) and `result` (from tool results) whenever `app()->environment('production')`, keeping only `id`/`name`. This exactly mirrors the existing live-stream gate so reopening a conversation in production can never reveal more than watching it live did.

**Alternative considered**: redact only sensitive-looking fields (e.g. header-shaped keys). Rejected — the live gate is all-or-nothing today; a partial redaction here would be an inconsistent, harder-to-reason-about security boundary than matching the existing one exactly.

### Carry tool activity as a host-only `tool_panels` field, not a `MessageBlock` variant
`resources/js/types/code-talker.d.ts` is a **vendor-published package contract** (`php artisan vendor:publish --tag=code-talker-types`; its own header says so), and `ChatMessageBubble.tsx` re-exports its `MessageBlock` verbatim specifically so a package-added block type "surfaces here as a type error instead of drifting silently." Widening that union to add a `tool` variant would be a `code-talker` change by definition — exactly what this proposal rules out.

The codebase already has the right shape for this and doesn't use `MessageBlock` for it: `ChatMessageBubble` already accepts a `toolPanels?: ToolPanel[]` prop, explicitly documented as "Host-only and live-only... dropped when the turn ends because the server never persists tool calls" (`ChatMessageBubble.tsx`). `ChatInterface.tsx`'s own `ChatMessage` is already documented as "Deliberately NOT the package's `ChatMessage`" for this exact reason (it covers host-only, client-built concerns). So: add `tool_panels?: ToolPanel[]` to that host `ChatMessage` interface, populate it from the host transcript endpoint for historical messages, and pass `item.msg.tool_panels` into the existing `toolPanels` prop at the one call site in `ChatVirtualList.tsx` that currently omits it (the `_kind === "message"` branch — the `_kind === "stream"` branch already wires `toolPanels` for the live row). No `MessageBlock`/package contract change at all. Update `ChatMessageBubble.tsx`'s doc comment once this ships, since "dropped... because the server never persists" will no longer be true.

**Alternative considered**: widen `MessageBlock` with a `tool` variant (the original draft of this decision). Rejected once `resources/js/types/code-talker.d.ts`'s own header was read closely — it is explicitly the package's versioned frontend contract, not host-owned, so this would violate the proposal's no-code-talker-changes goal.

### Fold `streamingToolPanels` into the persisted message at turn end
In `useChatStream.ts`, `persistLiveBlocks()` includes the turn's accumulated `streamingToolPanels` as that message's `tool_panels` when pushing it onto `messages`, instead of the panels being implicitly discarded once the stream ends (today nothing carries them into `messages` at all; `setStreamingToolPanels([])` at the next send/turn start is what currently erases them from view).

A related existing bug must be fixed for this to work in the all-tool-calls-no-trailing-text case: `persistLiveBlocks()` currently returns early via `if (liveBlocks.length === 0) return;`, so a turn that ends with only tool activity (no trailing text/reasoning delta) is never appended to `messages` at all today — not just missing its tool panel, missing entirely. The guard must become `if (liveBlocks.length === 0 && streamingToolPanels.length === 0) return;`.

**Alternative considered**: leave the live turn's tool panels out of the just-finished message and rely on a full page reload to backfill them from the server payload. Rejected — that means every fresh send would visibly lose its own tool activity until the next reload, which is worse than today's "it's visible during the stream" behavior for that one message.

## Risks / Trade-offs

- **[Risk]** A host-side transcript builder duplicates part of `ChatBotPresenter::transcript()`'s query shape → **Mitigation**: implement it as a thin wrapper that calls the package method for the base shape and enriches each row with `tool_calls`/`tool_results` from the same already-loaded `AiConversationMessage` collection, rather than re-querying or re-deriving `role`/`content`/`reasoning_content`/`blocks`.
- **[Risk]** Forgetting the production redaction in the new host code path would leak credentials that the live-stream gate was specifically added to protect → **Mitigation**: a feature test asserting a production-environment request never receives `arguments`/`result` in the transcript payload, mirroring the intent (if not the exact test) that presumably exists for the live gate.
- **[Risk]** `tool_calls` and `tool_results` are separate arrays, not a single ordered log → pairing them into one `ToolPanel` per tool run needs care to match calls to results (and to handle a call with no matching result, e.g. a turn cut off mid-tool-use by the duration guard) → **Mitigation**: `Laravel\Ai\Gateway\TextGenerationLoop::executeToolCalls()` constructs every `Data\ToolResult` with the *same* `id` as the `Data\ToolCall` it answers (confirmed by reading that method) — pair a persisted call to its result by exact `id` match; render an unresolved call (no matching result) the same way `ToolsPanel` already renders an in-flight tool (`isActive` state), rather than dropping it.
- **[Trade-off]** No success/failure indicator on historical tool results (Non-Goals) — acceptable because the model's own final text answer typically already reflects a failed tool call in its wording; a dedicated badge is a future, package-touching enhancement if wanted.

## Migration Plan

No database migration. No feature flag — this is additive rendering of already-stored data, ships in one deploy. Rollback is a plain revert (delete the persisted `tool_calls`/`tool_results` from the transcript payload and drop the new block type); no data is written or altered by this change.

## Open Questions

- Do we want a follow-up (touching `code-talker`) to persist `successful`/`error` alongside `tool_results` so historical tool runs can show a failure state? Deferred — flag for the user to decide after this ships.
- Should a tool call with no matching result (turn cut off by the max-duration guard mid-tool-use) render as "still running" or as an explicit "cut off" state? Proposed default: reuse the existing in-flight (`isActive`) visual from `ToolsPanel` per the mitigation above; revisit if it reads as misleading once seen against real data.
