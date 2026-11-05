## Why

AI usage is shown as a dollar figure wherever it appears, and the public chat page goes further: every visitor sees an "Overall Persona Cost" total for the persona and a "Cost:" line on each saved chat. The dollar amount is not something visitors should see, and in the admin a token count says what is needed without implying a billing figure the site does not reconcile. The cost also reaches the browser in page props even where it is not drawn, so hiding it in React alone would not keep it private.

## What Changes

- The admin Usage chip (`UsageChip`) shows the token total instead of a dollar amount, with the input/output split in its tooltip and no cost line. This affects every place it appears: the Applications list "AI Usage" column (renamed "Tokens"), the application details "Chat Usage" section and status bar, the AI Personas list "Usage" column, and the Conversations list and detail pages. The columns and sections stay.
- The chip no longer colours itself by spend; it uses one neutral style, since the old colour bands were dollar thresholds.
- The public chat history panel drops the "Overall Persona Cost" row and the per-chat "Cost:" line. `formatCost` (`resources/js/utils/currency.ts`) is deleted along with the `formatCost` prop chain.
- **BREAKING (page props)**: `cost_usd` is removed from every `usage` object the host sends (`ApplicationPayload::usage()`, `AiConversationController` index and show, `AiChatBotController` index), and `total_cost_usd` is removed from the chat page's `bot` prop (`HostChatBotPagePayload`). The TypeScript types (`ConversationUsage`, `ChatBotSummary`, `ChatHistoryEntry`) drop the fields.
- The AI Personas list decides whether a persona has usage from its token sums rather than its cost sum, and stops summing `usage_cost_usd`.
- Alongside: the Applications list "Updated" column shows the exact last-activity date and time in a tooltip on hover; rows gain `last_activity_at_iso` for it.
- Not changed: the database still records `usage_cost_usd`, and the code-talker usage sync and backfill commands keep computing it. Package-owned code in `jvjvjv/code-talker` is not edited.

## Capabilities

### New Capabilities

- `ai-usage-display`: how AI usage is shown in the admin and on the public chat page, and that no dollar cost reaches the browser.

### Modified Capabilities

None. `application-tracking` lists an "AI usage" column, which stays; its requirement does not say how usage is measured.

## Impact

- **Frontend**: `resources/js/admin/components/UsageChip.tsx`, `resources/js/chat/pages/ai/ChatBot.tsx`, `ChatHistoryPanel.tsx`, `chat-history-panel/ChatHistoryListCard.tsx`, `chat-history-panel/ChatHistoryListItem.tsx`, `resources/js/utils/currency.ts` (deleted), `resources/js/types/index.ts`, `resources/js/types/code-talker.d.ts`.
- **Backend**: `app/Support/ApplicationPayload.php`, `app/Http/Controllers/Admin/AiConversationController.php`, `app/Http/Controllers/Admin/AiChatBotController.php`, `app/Http/Controllers/Admin/ApplicationController.php` (PHPDoc shape), `app/Services/ChatBot/HostChatBotPagePayload.php`.
- **Tests**: `tests/Feature/ApplicationDiscussionTest.php` asserts `cost_usd` in the usage keys and must change; new assertions go in the existing controller and payload tests.
- **No** migration, dependency or package change. Usage recording tests (`ConversationUsageSyncTest` and the like) stay as they are.
