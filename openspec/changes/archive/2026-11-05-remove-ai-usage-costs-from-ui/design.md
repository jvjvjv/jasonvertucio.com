## Context

See proposal.md for why. Today a conversation's usage reaches the browser through four host-built shapes, all carrying `cost_usd`:

- `ApplicationPayload::usage()` — Applications list rows and the application page's `conversation.usage`.
- `AiConversationController::index()` and `show()` — each builds the same array inline.
- `AiChatBotController::index()` — sums `usage_input_tokens`, `usage_output_tokens` and `usage_cost_usd` across a persona's conversations, sends `total_tokens: null`, and uses the **cost** sum being non-null as its "has usage" test.
- `HostChatBotPagePayload::build()` — `bot.total_cost_usd` from the package's `ChatBotPresenter::totalCostUsd()`.

On the client, `UsageChip` is the only renderer in the admin; it returns an em dash when `cost_usd` is null and colours itself from six dollar bands. The chat page's per-chat `cost_usd` is declared in the types but never sent: `ChatBotSessionStore` history items carry no cost, so the per-chat line always reads "Cost: —".

## Goals / Non-Goals

**Goals:**

- One rule for showing usage, in `UsageChip`, based on tokens only.
- No `cost_usd` / `total_cost_usd` key in any host page prop.

**Non-Goals:**

- Changing how usage is recorded or synced, or dropping the `usage_cost_usd` column.
- Editing `jvjvjv/code-talker`. Its `AiPersonaManager` and `AiConversationManager` also build usage arrays with `cost_usd`, but the host does not render them in any page (the host only uses `AiPersonaManager`'s validation rules and write methods), so they are left alone.
- Merging the duplicated usage arrays in `AiConversationController` into a shared helper. Removing one key from each is smaller and keeps this change to the cost question.

## Decisions

**Token total comes from `total_tokens`, falling back to input + output.** `UsageChip` shows `total_tokens` when it is set, otherwise `input_tokens + output_tokens` when either is set, otherwise an em dash. Conversations sync all three, so the fallback matters only for the personas list, which today sends `total_tokens: null`. *Alternative:* always add input and output on the client. Rejected because a provider's total can include tokens outside the two (reasoning, cached), and the stored total is the better number when there is one.

**The personas list sums `usage_total_tokens` and tests for usage with token sums.** `AiChatBotController::index()` replaces `withSum('conversations', 'usage_cost_usd')` with `withSum('conversations', 'usage_total_tokens')`, sends that sum as `total_tokens`, and sends `usage: null` only when all three token sums are null. That keeps today's behaviour (a persona with no synced conversations shows an em dash) without reading cost.

**Abbreviate with `Intl.NumberFormat` compact notation.** `new Intl.NumberFormat("en-US", { notation: "compact", maximumFractionDigits: 1 })` gives `950`, `12.4K`, `1.2M`; the chip label is that number alone, with no unit. The Applications list column is renamed from "AI Usage" to "Tokens" so the unit lives in the heading there. The tooltip keeps the full `toLocaleString()` input and output counts it shows today. *Alternative:* a hand-written formatter. Rejected; the built-in already does the rounding and suffixes.

**The chip is one neutral style.** `variant="outlined"`, default colour, keeping today's size and weight. The colour bands were spend thresholds; token thresholds would differ per model and would bring the "is this expensive" signal back by another route. No tokens or colours change, so the admin-design lint rules are unaffected.

**Remove the cost from the chat panel by deleting it, not hiding it.** `ChatHistoryListCard` loses the "Overall Persona Cost" row and its `totalCostUsd`/`formatCost` props; `ChatHistoryListItem` loses the "Cost:" text and its `formatCost` prop; `ChatHistoryPanel` and `ChatBot.tsx` stop passing them. With no caller left, `resources/js/utils/currency.ts` is deleted. The `updated_at` text in each history item stays left-aligned where it is.

**Types follow the payloads.** `ConversationUsage` drops `cost_usd`; `ChatBotSummary` drops `total_cost_usd`; `ChatHistoryEntry` and the local `HistoryItem`/`Bot` interfaces in the chat panel drop their cost fields. `code-talker.d.ts`'s header says the page-prop half is host-owned, so editing it is safe; the edit is to that half only.

## Risks / Trade-offs

- [A future package upgrade re-publishes `code-talker.d.ts`] → The file's header already says to hand-merge; the removed fields are in the host-owned half, which a hand-merge keeps.
- [Something else reads `cost_usd` from these props] → A repo-wide search found only `UsageChip`, the chat panel chain and the types. TypeScript (`npm run build`) catches any missed reader once the fields leave the types.
- [Personas list total is a sum of stored totals, so a conversation with input/output but no total contributes nothing to it] → Sync writes all three together; a conversation with partial counts is not a state the sync produces.

## Migration Plan

No data migration. Deploy is the usual build (`npm run build`) plus the queue-worker restart CLAUDE.md requires; nothing here touches queued code. Rollback is reverting the commit.
