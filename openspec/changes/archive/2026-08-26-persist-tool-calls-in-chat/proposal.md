## Why

When a chat bot calls a tool (web search, an MCP tool, or any other registered tool), the tool call/result is visible only for the instant it streams live. The moment that turn finishes — even without a reload — the tool activity disappears from the UI, and it never reappears after a page reload or when switching back into an older conversation. This isn't a data problem: `jvjvjv/code-talker` already persists `tool_calls`/`tool_results` on every `AiConversationMessage` row via `TurnRecorder`. The app simply never reads those columns back out when building the chat page, and the frontend has nowhere to render them for a historical message even if it did. Chat bots that lean on tools (which is most of them, given `supports_tools` is a first-class `AiSystem` setting) currently look like they silently "forget" what they did.

## What Changes

- Add a host-owned transcript path that includes each message's `tool_calls`/`tool_results` (in addition to what `Jvjvjv\CodeTalker\Services\ChatBot\ChatBotPresenter::transcript()` already provides), wired into `App\Services\ChatBot\HostChatBotPagePayload`.
- Extend the host-only `ChatMessage` type (`resources/js/components/ChatInterface.tsx` — already documented as deliberately distinct from the package's published `ChatMessage`) with a `tool_panels` field reusing the existing host-only `ToolPanel` shape, so persisted tool data renders through the same `ToolsPanel` component already used for live tool activity. This does not touch the package-published `MessageBlock`/`ChatMessage` contract in `resources/js/types/code-talker.d.ts`.
- Change `useChatStream`'s turn-completion handling so the in-flight `streamingToolPanels` state for a turn is folded into the message object appended to `messages`, instead of being discarded once the stream ends.
- Update the historical-message renderer (`ChatMessageBubble` / `ChatVirtualList`) to display a tool block for any message that has one, not only for the row currently streaming.
- No changes to `jvjvjv/code-talker`: its conversation storage, `TurnRecorder`, and `AiConversationMessage` schema already capture everything needed.

## Capabilities

### New Capabilities
- `chat-tool-call-persistence`: Tool calls and their results made during a chat turn survive that turn's completion and subsequent reloads, rendered consistently for both the live stream and historical conversation messages.

### Modified Capabilities
(none — no existing spec constrains transcript message content or tool-panel rendering)

## Impact

- **Affected code (jasonvertucio.com only)**:
  - `app/Services/ChatBot/HostChatBotPagePayload.php` (or a new host transcript builder it delegates to)
  - `resources/js/components/ChatInterface.tsx` (`ChatMessage`, `MessageBlock` types)
  - `resources/js/hooks/useChatStream.ts` (turn-completion / `persistLiveBlocks` handling)
  - `resources/js/components/ChatMessageBubble.tsx` and/or `resources/js/components/chat-interface/ChatVirtualList.tsx`
- **Not affected**: `jvjvjv/code-talker` package (storage, `TurnRecorder`, `ConversationTurnRunner`, migrations) — already correct and sufficient.
- **Data**: No new migrations; reads existing `tool_calls`/`tool_results` columns on `ai_conversation_messages`.
