## Context

This is a pure string-literal content change in the frontend — no architecture, data model, or dependency involved. A design doc is included only because the schema requires it before `tasks.md`; the substance here is a short scope boundary, not a technical design.

## Goals / Non-Goals

**Goals:**
- Every rendered occurrence of "Chat Bot(s)"/"ChatBot(s)"/"Chatbot(s)"/"chatbot(s)" becomes "Agent(s)" (matching the surrounding capitalization convention already used at each call site — title case in headings/labels, lowercase in inline prose).
- The full inventory (below) is the complete list — nothing found by an exhaustive grep of `resources/js/**/*.{ts,tsx}` for `chat.?bot` (case-insensitive) was left out deliberately, other than identifiers explicitly excluded per Non-Goals.

**Non-Goals:**
- Routes, URL paths, route names — unchanged per explicit decision (the app still uses `/chats`, `/chat/{slug}`; "chat" names the activity, "agent" names the entity).
- Class names, file names, prop names, JSON/API field names (`chat_bots_count`, `ai_chat_bot_id`, `ai_chat_bot_name`, `ai_chat_bot_slug`, etc.) — these mirror the still-`AiChatBot`-named `jvjvjv/code-talker` base classes/relations and the app's own `App\Models\AiChatBot`; an upstream code-talker rename is expected later and will drive renaming these in one pass instead of twice.
- Any `jvjvjv/code-talker` package file.

## Decisions

### Full inventory of strings to change (file:line → old → new)
1. `resources/js/admin/pages/ai/bots/Index.tsx:178` — `<Head title="Chat Bots | AI Tools" />` → `"Agents | AI Tools"`
2. `Index.tsx:180` — page heading `"AI Chat Bots"` → `"AI Agents"`
3. `Index.tsx:169` — `` `Delete AI chat bot "${bot.name}"?` `` → `` `Delete AI agent "${bot.name}"?` ``
4. `Index.tsx:256` — `"No AI chat bots configured yet."` → `"No AI agents configured yet."`
5. `resources/js/admin/pages/ai/bots/Create.tsx:56` — `<Head title="New | Chat Bots" />` → `"New | Agents"`
6. `Create.tsx:58` — `title="Add AI Chat Bot"` → `"Add AI Agent"`
7. `Create.tsx:60` — `backLabel="Back to AI Chat Bots"` → `"Back to AI Agents"`
8. `resources/js/admin/pages/ai/bots/Edit.tsx:68` — `` <Head title={`${bot.name} | Chat Bots`} /> `` → `` `${bot.name} | Agents` ``
9. `Edit.tsx:61` — `` `Delete AI chat bot "${bot.name}"?` `` → `` `Delete AI agent "${bot.name}"?` ``
10. `Edit.tsx:72` — `backLabel="Back to AI Chat Bots"` → `"Back to AI Agents"`
11. `resources/js/admin/pages/ai/systems/Edit.tsx:96` — `` `Chat Bots (${aiSystem.chat_bots_count || 0})` `` → `` `Agents (${aiSystem.chat_bots_count || 0})` `` (the `chat_bots_count` field name itself is unchanged — Non-Goals)
12. `resources/js/admin/pages/ai/systems/Index.tsx:131` — `` `"${system.name}" is used by ${botCount} chat bot(s)...` `` → `` `... ${botCount} agent(s)...` ``
13. `Index.tsx:176` — nav link text `"AI Chat Bots"` → `"AI Agents"`
14. `resources/js/admin/pages/ai/systems/Create.tsx:124` — `"...chat bots on this system cannot use MCP tools."` → `"...agents on this system cannot use MCP tools."`
15. `resources/js/admin/pages/ai/systems/Edit.tsx:176` — same text, same change
16. `resources/js/chat/pages/ai/ChatBotsIndex.tsx:78` — `"Available Chatbots"` → `"Available Agents"`
17. `ChatBotsIndex.tsx:81` — `"Start a new chat with any chatbot available to you."` → `"Start a new chat with any agent available to you."`
18. `ChatBotsIndex.tsx:89` — `"No chatbots are currently available."` → `"No agents are currently available."`
19. `resources/js/chat/pages/ai/ChatBot.tsx:134` — `"Chatbots are experimental. Responses may be..."` → `"Agents are experimental. Responses may be..."`
20. `resources/js/chat/pages/ai/chat-history-panel/ChatHistoryListCard.tsx:65` — `"Overall Chatbot Cost"` → `"Overall Agent Cost"`
21. `resources/js/components/ChatBotCard.tsx:71` — `"No description is available for this chatbot yet."` → `"No description is available for this agent yet."`
22. `resources/js/admin/pages/ai/conversations/Index.tsx:67` — `` {row.ai_chat_bot_name ?? "No bot"} `` → `"No agent"`
23. `resources/js/chat/components/BotHeaderCard.tsx:22` — `"AI Chat Bot"` → `"AI Agent"`

Found during implementation — standalone "Bot" (not matching the original `chat.?bot` grep) in the same UI vocabulary, same rename:

24. `resources/js/admin/pages/ai/bots/Index.tsx:214` — `"Add Bot"` → `"Add Agent"`
25. `resources/js/admin/pages/ai/bots/Create.tsx:95` — `"Save Bot"` → `"Save Agent"`
26. `resources/js/admin/pages/ai/bots/Edit.tsx:123` — `"Update Bot"` → `"Update Agent"`
27. `resources/js/admin/pages/ai/conversations/Index.tsx:62` — `"System / Bot"` (column label) → `"System / Agent"`
28. `resources/js/admin/pages/ai/conversations/Index.tsx:244` — `label="Bot"` (filter field) → `"Agent"`
29. `resources/js/admin/pages/ai/conversations/Show.tsx:176` — `<strong>Bot:</strong>` → `<strong>Agent:</strong>`
30. `resources/js/admin/pages/ai/systems/Index.tsx:131` — same string also contains `"...will deactivate those bots..."` → `"...deactivate those agents..."` (same edit as #12, just the fuller quote)

**Follow-up correction**: after reviewing every on-screen occurrence of the literal phrase "AI Agent" (items #2, #6, #7, #10, #13, #23 above), the "AI" prefix was dropped from all six — they now read plain "Agent(s)" ("Agents", "Add Agent", "Back to Agents" ×2, "Agent"), matching how the rest of the renamed UI never said "AI Chatbot" either (`ChatBotsIndex.tsx`'s "Available Chatbots"/"chatbot" wording, `ChatBotCard.tsx`, `ChatHistoryListCard.tsx` were all plain, un-prefixed "chatbot"/"Chatbot" before this change).

No design alternatives to weigh — this is an enumerated find-and-replace, not a decision with trade-offs.

## Risks / Trade-offs

- **[Risk]** Missing an occurrence that a future grep would have caught → **Mitigation**: tasks.md includes a final verification grep for the same `chat.?bot` pattern (case-insensitive) across `resources/js/**/*.{ts,tsx}`, asserting zero remaining matches outside the explicitly-excluded identifier categories (Non-Goals).
- **[Trade-off]** The UI will now say "Agent" while `chat_bots_count`/`ai_chat_bot_*` field names and the underlying `/chat/{slug}` URLs still say "chat bot" internally — a short-lived naming inconsistency between what's displayed and what's in code/URLs, accepted deliberately per the Non-Goals rationale (avoiding a double rename once code-talker's own rename lands).
