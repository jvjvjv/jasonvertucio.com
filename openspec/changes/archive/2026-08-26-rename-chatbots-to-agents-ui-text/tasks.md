## 1. Admin bot management pages

- [x] 1.1 `resources/js/admin/pages/ai/bots/Index.tsx` — update the `<Head>` title, page heading, delete-confirmation string, and empty-state string (4 strings, see design.md #1–4).
- [x] 1.2 `resources/js/admin/pages/ai/bots/Create.tsx` — update the `<Head>` title, form title, and back-link label (3 strings, see design.md #5–7).
- [x] 1.3 `resources/js/admin/pages/ai/bots/Edit.tsx` — update the `<Head>` title, delete-confirmation string, and back-link label (3 strings, see design.md #8–10).

## 2. AI system pages

- [x] 2.1 `resources/js/admin/pages/ai/systems/Edit.tsx` — update the "Chat Bots (count)" label and the MCP-tools description text (2 strings, see design.md #11, #15).
- [x] 2.2 `resources/js/admin/pages/ai/systems/Index.tsx` — update the usage-count message and the nav link label (2 strings, see design.md #12–13).
- [x] 2.3 `resources/js/admin/pages/ai/systems/Create.tsx` — update the MCP-tools description text (1 string, see design.md #14).

## 3. Conversations admin page

- [x] 3.1 `resources/js/admin/pages/ai/conversations/Index.tsx` — update the "No bot" fallback string (see design.md #22).

## 4. Public chat surface

- [x] 4.1 `resources/js/chat/pages/ai/ChatBotsIndex.tsx` — update the heading, subheading, and empty-state strings (3 strings, see design.md #16–18).
- [x] 4.2 `resources/js/chat/pages/ai/ChatBot.tsx` — update the experimental-notice string (see design.md #19).
- [x] 4.3 `resources/js/chat/pages/ai/chat-history-panel/ChatHistoryListCard.tsx` — update the "Overall Chatbot Cost" label (see design.md #20).
- [x] 4.4 `resources/js/components/ChatBotCard.tsx` — update the no-description fallback string (see design.md #21).
- [x] 4.5 `resources/js/chat/components/BotHeaderCard.tsx` — update the "AI Chat Bot" label (see design.md #23).

## 5. Verification

- [x] 5.1 Run `grep -rniE "chat.?bot" resources/js --include="*.tsx" --include="*.ts"` and confirm every remaining match is an identifier/route/field name excluded per design.md's Non-Goals (class names, prop names, `ai_chat_bot_*`/`chat_bots_count` field names, route paths, imports of package types) — not rendered UI text.
- [x] 5.2 Run `npx tsc --noEmit -p .` — no new type errors (string literal changes only, should be a no-op check).
- [x] 5.3 Run `npm run build` — succeeds.
- [ ] 5.4 Spot-check in a running dev server: admin agents list/create/edit pages, an AI system's edit page, the public agents index, and an individual chat page all read "Agent(s)" where they previously read "Chat Bot(s)"/"Chatbot(s)".
