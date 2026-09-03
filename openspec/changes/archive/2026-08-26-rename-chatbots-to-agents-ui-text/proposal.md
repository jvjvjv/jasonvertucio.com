## Why

The product wants "Chat Bots" to read as "Agents" to visitors and admins — the first step of a broader UI cleanup. Scoped narrowly per decision: only what users actually read changes now. URLs (`/chats`, `/chat/{slug}`) stay as-is — "chat" describes the activity of chatting *with* an agent, not the entity name, so no rename needed there. Internal PHP/TS identifiers (`App\Models\AiChatBot`, `ChatBotController`, `App\Services\ChatBot\*`, `ChatBot.tsx`, the `chat_bots_count`/`ai_chat_bot_*` field names) also stay untouched for now: they mirror the still-`AiChatBot`-named base classes and relations in `jvjvjv/code-talker` (`Jvjvjv\CodeTalker\Models\AiChatBot`, `AiSystem::chatBots()`, the published `ChatBot*` TypeScript contract), and a separate upcoming code-talker change is expected to rename those concepts at the source — reworking the app's internal naming twice would be wasted effort.

## What Changes

- Every user-visible occurrence of "Chat Bot(s)" / "ChatBot(s)" / "Chatbot(s)" / "chatbot(s)" in rendered UI text — page titles (`<Head>`), headings, nav link labels, empty states, confirmation dialogs, descriptions, and card labels — becomes "Agent(s)", across both the public chat surface (`resources/js/chat/`) and the admin AI tools surface (`resources/js/admin/pages/ai/`).
- No change to: routes, URL paths, route names, component/page file names, class names, prop names, JSON/API field names, database columns, or the `jvjvjv/code-talker` package.

## Capabilities

### New Capabilities
- `agent-terminology`: User-facing text refers to chat bots as "Agent(s)", not "Chat Bot(s)"/"Chatbot(s)" — an enduring naming convention future UI text should follow, not just a one-time text swap.

### Modified Capabilities
(none — no existing spec governs UI copy/wording)

## Impact

- **Affected code**: ~13 frontend files, string literals only (list enumerated in tasks.md) — `resources/js/admin/pages/ai/bots/{Index,Create,Edit}.tsx`, `resources/js/admin/pages/ai/systems/{Index,Create,Edit}.tsx`, `resources/js/admin/pages/ai/conversations/Index.tsx`, `resources/js/chat/pages/ai/{ChatBot,ChatBotsIndex}.tsx`, `resources/js/chat/pages/ai/chat-history-panel/ChatHistoryListCard.tsx`, `resources/js/chat/components/BotHeaderCard.tsx`, `resources/js/components/ChatBotCard.tsx`.
- **Not affected**: `jvjvjv/code-talker` package; any route, file, class, or field name; the database.
- **Follow-up (explicitly not this change)**: renaming the underlying classes/models/relations once the upstream code-talker rename lands.
