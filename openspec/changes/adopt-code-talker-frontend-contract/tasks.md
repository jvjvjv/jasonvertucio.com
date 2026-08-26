## 1. Publish the package declarations

- [x] 1.1 Run `php artisan vendor:publish --tag=code-talker-types` and confirm it writes `resources/js/types/code-talker.d.ts`. Use `--force` if the file already exists, since the published copy must match the installed package.
- [x] 1.2 Confirm the file resolves through the existing `@/*` alias (`tsconfig.json` paths and `vite.config.js` both map `@` to `resources/js`), so the import specifier is `@/types/code-talker`. Do not move or rename the published file — a later `--force` re-publish must land on the same path.
- [x] 1.3 Sanity-check that the published declarations match this app's reality: `ChatBotSummary` should carry `total_cost_usd`, and `ChatBotListEntry` should carry `conversations`. Both were confirmed present when this change was proposed; a mismatch means the installed package drifted and should be investigated before continuing.

## 2. Replace the hand-rolled StreamEvent

- [x] 2.1 In `resources/js/components/ChatInterface.tsx`, delete the `StreamEvent` interface (lines ~47-64) and replace it with a discriminated union built from the package type: `export type StreamEvent = ChatStreamEvent | ToolUseProgressEvent | PageReloadEvent`. Import `ChatStreamEvent` from `@/types/code-talker`. **Do not carry over the `[key: string]: unknown` index signature** — removing it is the point of the change.
- [x] 2.2 Declare the two host-only event interfaces alongside it: `ToolUseProgressEvent` (`type: 'tool_use_progress'`, `text: string`, `tools: string[]`) and `PageReloadEvent` (`type: 'page_reload'`). Document each with the emitting server line — `app/Services/TargetedResumeService.php:281` and `:300` respectively — so they are visibly host extensions rather than package contract.
- [x] 2.3 Keep `StreamEvent` exported from `ChatInterface.tsx`. Both `useChatStream.ts:3` and the `onEvent` prop import it from there; re-homing the type is churn beyond this change's scope.
- [x] 2.4 Consider re-exporting the package's `ChatMessage`/`MessageBlock` instead of the local declarations if they prove identical. If they differ in any field, keep the local ones and note the divergence in a comment — do not silently widen the app's types to match.

## 3. Tighten the stream hook

- [x] 3.1 In `resources/js/hooks/useChatStream.ts`, delete the `thinking_delta` branch (lines ~236-243): the `event.delta?.type === "thinking_delta" && event.delta.thinking` arm inside the `content_block_delta` handler. Keep the sibling `event.delta?.text` arm, which is the real one.
- [x] 3.2 Drop optional chaining the union now makes unnecessary — `event.delta.reasoning` on `reasoning_block_delta`, `event.delta.text` on `content_block_delta`, `event.text` / `event.tools` on `tool_use_progress`. Leave `event.message ?? "..."` fallbacks alone where the package type genuinely allows the field to be absent.
- [x] 3.3 Verify the `status` branch still compiles: the package's `StatusEvent` declares `message: string` as required, so any `?? "Waiting for model response..."` fallback there may now be flagged as unnecessary. Keep the fallback only if the type permits it.
- [x] 3.4 Confirm the final `else if (event.type === "error")` branch still reads `event.reason`, which the package types as the optional `ChatStreamErrorReason` union. The `streamErrorReason` variable that drives benign-abort detection should now be typed from that union rather than `string | undefined`.
- [x] 3.5 Leave the streaming logic itself untouched — RAF batching, `appendToBlocks` coalescing, `persistLiveBlocks`, and the benign-abort heuristic in the catch block. This change must not alter behavior.

## 4. Extend the page props

- [x] 4.1 In `resources/js/chat/pages/ai/ChatBot.tsx`, replace the locally-declared prop fields that duplicate the package's `ChatBotPageProps` (`messageUrl`, `resetUrl`, `switchUrl`, `statusUrl`, `warmupUrl`, `chatUrl`, `chatUrlBase`, `showIdentityForm`, `messages`, `history`) with an `extends ChatBotPageProps`.
- [x] 4.2 Keep the host's two additions explicit on top of the extension: `bot.allowed_roles` and `previousHref`. These come from `host-chat-bot-presentation` and are not package contract — a comment should say so.
- [x] 4.3 Check whether `ChatBotsIndex.tsx` can likewise extend `ChatBotsIndexProps`. It carries one of the eight baseline type errors, so if extending it entangles with that error, leave it and note why — fixing the baseline is out of scope.

## 5. Verification

- [x] 5.1 Run `npx tsc --noEmit` and compare against the recorded baseline: **8 errors across 4 files** (`admin/pages/ai/bots/Index.tsx` ×4, `admin/pages/ai/memories/Edit.tsx` ×2, `admin/pages/ai/system-prompts/Index.tsx` ×1, `chat/pages/ai/ChatBotsIndex.tsx` ×1). The gate is no new errors and **zero errors in `ChatInterface.tsx`, `useChatStream.ts`, and `ChatBot.tsx`**.
- [x] 5.2 Confirm the union actually discriminates rather than merely compiling: temporarily read a nonexistent property (e.g. `event.mesage`) inside a narrowed branch and verify `tsc` errors on it, then remove the probe. Without this the change could pass while the index signature was silently reintroduced somewhere.
- [x] 5.3 Grep `resources/js/` for `thinking_delta` and `delta.thinking` and confirm no matches remain.
- [x] 5.4 Confirm `BuilderChatPanel.tsx:215` still compiles against the union — its `event.type === "page_reload"` check is the reason `PageReloadEvent` is in there at all.
- [x] 5.5 Run `npm run build` and confirm the bundle builds. Run `npm run lint` on the touched files and confirm no new lint errors (ignore any Prettier complaints per project convention).
- [x] 5.6 Run `vendor/bin/phpunit` and confirm the PHP suite is still green at 288 tests — this change should be a no-op server-side, so any movement means something unintended happened.
- [ ] 5.7 Exercise the targeted-resume builder end to end: start a build, confirm the tool activity indicator appears (`tool_use_progress`) and the panel reloads after a save (`page_reload`). These two events have no automated coverage, so this is the only check that they still flow.

## 6. Record the upstream follow-up

- [x] 6.1 Add a `code-talker` change proposing `tool_use_progress` and `page_reload` as first-class package stream events — emitted by `ConversationTurnRunner` (which already collects `ToolCallEvent`s at the right point) and added to the `ChatStreamEvent` union and README contract. Reference the analysis in this change's `design.md`.
- [x] 6.2 Note in that proposal that migrating `TargetedResumeService` onto `ConversationTurnRunner` is the follow-on win, and is blocked until 6.1 ships. Carry over the gap table from `design.md`: the host loop lacks client-abort detection, the per-step max-duration guard, non-recoverable `ErrorEvent` handling, `RawExchangeContext` recording, and `TurnSequence::labelFor()`.
- [x] 6.3 Separately note the `api.stream` framing improvements worth borrowing from the published client — split on blank lines rather than single newlines, tolerate `data:` without a space, join multi-line `data:` fields, and flush the decoder tail after the read loop — as a robustness fix that keeps `api.stream`'s 419 retry and session hooks intact.
