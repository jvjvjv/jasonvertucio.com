## 1. Host transcript: read and redact tool data

- [x] 1.1 In `app/Services/ChatBot/HostChatBotPagePayload.php` (or a small new host service it delegates to), build the transcript by calling the package's `ChatBotPresenter::transcript()` for the base shape, then enrich each row using the same `AiConversation`'s already-loaded `AiConversationMessage` records' `tool_calls`/`tool_results` columns — do not re-derive `role`/`content`/`reasoning_content`/`blocks`.
- [x] 1.2 Pair each message's `tool_calls` entries to `tool_results` entries by exact `id` match (`Laravel\Ai\Gateway\TextGenerationLoop::executeToolCalls()` constructs every result with the calling `ToolCall`'s `id`). A call with no matching result is still included, with no result data.
- [x] 1.3 Build each message's tool data as an array shaped like the host's `ToolPanel` (`resources/js/components/ToolsPanel.tsx`): `{ tools: [name], input?, output? }` per paired call/result — mirroring the same merge `useChatStream.ts` already does live for a call+result pair.
- [x] 1.4 Apply production redaction: when `app()->environment('production')`, omit `input` (from the call's `arguments`) and `output` (from the result's `result`) entirely — keep only the tool name(s). Do not gate this on anything else (e.g. auth), matching `ChatBotController::message()`'s existing unconditional-outside-production gate.
- [x] 1.5 A message with no tool activity gets no tool data field (omitted/null), not an empty array — matches the "no tool data" scenario in the spec.
- [x] 1.6 Add/extend a feature test (see existing `tests/Feature/HostChatBotPagePayloadTest.php`) asserting: a message with tool activity includes it in non-production; a production request never includes `input`/`output` for any message; a message without tool activity has no tool data.

## 2. Frontend: host-only `tool_panels` field, not a `MessageBlock` change

- [x] 2.1 Add `tool_panels?: ToolPanel[]` to the host-only `ChatMessage` interface in `resources/js/components/ChatInterface.tsx` (import `ToolPanel` from `@/components/ToolsPanel`). Do NOT touch `resources/js/types/code-talker.d.ts` or `MessageBlock` — that file is the package's published, versioned contract.
- [x] 2.2 In `resources/js/components/chat-interface/ChatVirtualList.tsx`, pass `toolPanels={item.msg.tool_panels}` on the `_kind === "message"` branch's `<ChatMessageBubble>` call (the `_kind === "stream"` branch already wires this for the live row).
- [x] 2.3 Update `ChatMessageBubble.tsx`'s `toolPanels` prop doc comment — it currently says panels are "dropped when the turn ends because the server never persists tool calls," which will no longer be true.

## 3. Frontend: stop discarding a finished turn's tool activity

- [x] 3.1 In `resources/js/hooks/useChatStream.ts`, change `persistLiveBlocks()`'s early-return guard from `if (liveBlocks.length === 0) return;` to `if (liveBlocks.length === 0 && streamingToolPanels.length === 0) return;` so a turn that ends with only tool activity (no trailing text/reasoning) still gets appended to `messages`.
- [x] 3.2 In the same function, include the turn's accumulated `streamingToolPanels` as `tool_panels` on the message object pushed onto `messages`.
- [x] 3.3 Verify `streamingToolPanels` is still correctly cleared at the start of the next turn/send (existing `setStreamingToolPanels([])` calls) — this task only changes what happens at turn-end persistence, not the reset points.

## 4. Verification

- [x] 4.1 Run `cd /home/jasonv/Code/@jvjvjv/jasonvertucio.com && vendor/bin/phpunit` — full suite passes, including the new/extended transcript test from 1.6.
- [x] 4.2 Run `npx tsc --noEmit -p .` — no new type errors introduced by the `tool_panels` additions.
- [x] 4.3 Run `npm run build` — succeeds.
- [ ] 4.4 Manually verify in a running chat bot with `supports_tools` enabled and a tool actually invoked (non-production): tool panel is visible while streaming, remains visible immediately after the turn finishes (no reload), remains visible after a full page reload, and remains visible when switching away to another conversation and back.
- [ ] 4.5 Manually verify with `APP_ENV=production` (or by temporarily forcing the environment check) that a reloaded transcript with tool activity shows the tool name(s) but no arguments/output.
- [x] 4.6 Confirm no changes were made under `/home/jasonv/Code/@jvjvjv/code-talker` for this change.
