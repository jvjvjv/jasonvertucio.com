## 1. Stop sending cost in page props (tests first)

- [x] 1.1 In `tests/Feature/ApplicationDiscussionTest.php`, change the `conversation.usage` assertion to `hasAll(['input_tokens', 'output_tokens', 'total_tokens', 'synced_at'])` plus `->missing('cost_usd')`; add the same `missing('cost_usd')` check for an Applications list row with a session in `tests/Feature/ApplicationListTest.php`. Run both files and confirm the new assertions fail.
- [x] 1.2 Remove `cost_usd` from `ApplicationPayload::usage()` and update its PHPDoc shape and summary line (drop "and cost"), and the matching `usage` shape in `ApplicationController`'s PHPDoc. Verify with `php artisan test --compact tests/Feature/ApplicationDiscussionTest.php tests/Feature/ApplicationListTest.php`.
- [x] 1.3 In `tests/Feature/AiConversationControllerTest.php`, give the conversations in `test_index_lists_conversations_across_features` and `test_show_displays_messages_and_memories` synced usage values and assert their `usage` has `input_tokens`, `output_tokens`, `total_tokens` and `synced_at` and is missing `cost_usd`. Confirm they fail, then remove `cost_usd` from both inline usage arrays in `AiConversationController`, and confirm the file passes.
- [x] 1.4 In `tests/Feature/AiChatBotControllerTest.php`, add tests that (a) a persona with two synced conversations of 1,000 and 3,000 total tokens gets `usage.total_tokens` 4000, summed input/output tokens and no `cost_usd`, and (b) a persona with no synced conversations gets `usage` null. Confirm they fail, then in `AiChatBotController::index()` replace the `usage_cost_usd` sum with a `usage_total_tokens` sum, send it as `total_tokens`, drop `cost_usd`, and return `null` only when all three token sums are null. Confirm the file passes.
- [x] 1.5 In `tests/Feature/HostChatBotPagePayloadTest.php`, add a test that the chat page's `bot` prop is missing `total_cost_usd`. Confirm it fails, remove the key (and the "/cost" wording in the class docblock) from `HostChatBotPagePayload::build()`, and confirm the file passes.
- [x] 1.6 Confirm cost is still recorded: `php artisan test --compact tests/Feature/ConversationUsageSyncTest.php` passes unchanged (its `usage_cost_usd` assertions cover the "Cost is still recorded" scenario).

## 2. Types

- [x] 2.1 Remove `cost_usd` from `ConversationUsage` in `resources/js/types/index.ts`, and `cost_usd` / `total_cost_usd` (with its "Lifetime spend" comment) from `ChatHistoryEntry` and `ChatBotSummary` in the host-owned half of `resources/js/types/code-talker.d.ts`. Verify that `npx tsc --noEmit` (or `npm run build`) now reports errors only in the files changed in sections 3 and 4.

## 3. Admin usage chip

- [x] 3.1 Rewrite `resources/js/admin/components/UsageChip.tsx`: total = `total_tokens`, else `input_tokens + output_tokens` when either is set, else em dash; label is `Intl.NumberFormat("en-US", { notation: "compact", maximumFractionDigits: 1 })` output alone (no unit); tooltip lists input and output tokens only; one neutral style (`variant="outlined"`, default colour), no cost bands. Verify no `cost` remains in the file.
- [x] 3.2 Check the six call sites (`applications/Index.tsx`, `applications/StatusBar.tsx`, `applications/DetailsForm.tsx`, `ai/bots/Index.tsx`, `ai/conversations/Index.tsx` and `Show.tsx`) still type-check unchanged, since the chip's props do not change. Verify with `npx tsc --noEmit`.
- [x] 3.3 Rename the Applications list column heading in `applications/Index.tsx` from "AI Usage" to "Tokens".
- [x] 3.4 Applications list "Updated" column: send `last_activity_at_iso` alongside the humanised `last_activity_at` from `ApplicationController::listRow()` (covered in `ApplicationListTest`), and show it as a localized date/time tooltip (`getLocaleDateTime`) on hover.

## 4. Public chat panel

- [x] 4.1 Remove the "Overall Persona Cost" row and the `totalCostUsd` / `formatCost` props from `chat-history-panel/ChatHistoryListCard.tsx`, the "Cost:" text and `formatCost` prop from `chat-history-panel/ChatHistoryListItem.tsx` (leaving `updated_at` in place), the `formatCost` prop and cost fields from `ChatHistoryPanel.tsx`, and the `formatCost` import and prop from `ChatBot.tsx`. Verify with `npx tsc --noEmit`.
- [x] 4.2 Delete `resources/js/utils/currency.ts` after confirming `grep -rn "formatCost\|utils/currency" resources/js` returns nothing.

## 5. Verify

- [x] 5.1 `grep -rniE "cost_usd|total_cost|formatCost" resources/js app/Http app/Support app/Services/ChatBot` returns nothing outside `usage_cost_usd` model/sync references; `npm run build` succeeds; ESLint passes on the changed files (fix any Prettier issues in them before committing).
- [x] 5.2 Run `php artisan test --compact` on the five test files touched above and confirm they pass; then ask whether to run the full suite.
- [x] 5.3 In the local Docker site (Vivaldi), capture screenshots of the Applications list, an application's details panel, the AI Personas list, a conversation detail page and a public chat's history panel, and show them to the developer before merging to develop.
