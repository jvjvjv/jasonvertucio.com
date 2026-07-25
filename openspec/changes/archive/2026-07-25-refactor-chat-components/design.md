## Context

`ChatInterface.tsx` (757 lines) is a `forwardRef` component that owns SSE stream parsing, model-status polling/warmup, session-expiry wiring, auto-start-on-mount, and a virtualized message list, all in one file with 12+ `useState` calls and a single 220-line `sendMessage` callback. It is reused by three consumers (`ChatBot.tsx`, `BuilderChatPanel.tsx`, and indirectly `Show.tsx` via a type import), so any change to it currently requires understanding the whole file to avoid breaking one of those consumers. `ChatMessageBubble.tsx` (329 lines) has two near-duplicate render paths (block-based and legacy) that repeat the same markdown-render and "sent at" label JSX. `ChatBot.tsx` (219 lines) and `ChatHistoryPanel.tsx` (228 lines) mix presentational JSX with small pure-logic concerns (badge-color derivation, cost formatting, URL-sync effect).

The codebase already has two established organizational patterns to build on:
- A flat `resources/js/hooks/` directory (`useConfirmDialog`, `useDeviceInfo`, `useSessionExpiry`) — one hook per file, default export, JSDoc rationale comment.
- Page-local decomposition via flat sibling files in the same directory as the page (e.g. `admin/pages/resume/targeted/Show.tsx` delegating to sibling `BuilderChatPanel.tsx`, `BuilderStatusCard.tsx`, etc.).

The user explicitly asked for single-use pieces to live in **their own files, in a subdirectory** next to the component they were extracted from — this is a slight departure from the flat-sibling precedent, applied specifically to this refactor.

## Goals / Non-Goals

**Goals:**
- Reduce `ChatInterface.tsx` and `ChatMessageBubble.tsx` to orchestration-only files under ~200 lines each.
- Move genuinely reusable, stateful logic (stream handling, model status, session/URL sync) into hooks under `resources/js/hooks/`, matching the existing flat-hook convention.
- Move single-use presentational pieces into a colocated subdirectory named after the file they came from (kebab-case), per the user's explicit request.
- Preserve all existing public props/types/exports so consumers need zero logic changes (only import-path updates where they import a symbol that moved).
- De-duplicate the repeated markdown/timestamp JSX in `ChatMessageBubble.tsx`.

**Non-Goals:**
- No behavior changes, no new features, no visual changes.
- No changes to `resources/js/admin/pages/resume/targeted/Show.tsx` logic (only import paths if needed).
- No introduction of a state-management library (Redux/Zustand) — hooks + props remain the mechanism.
- No barrel (`index.ts`) files — each extracted file is imported directly by its explicit path, matching how the rest of the codebase imports components today (no existing barrel-file precedent found).

## Decisions

**1. Subdirectory naming: kebab-case of the source file, colocated with it.**
`ChatInterface.tsx` → `resources/js/components/chat-interface/` (siblings: `EmptyPlaceholder.tsx`, `ChatVirtualList.tsx`, `SessionExpiryBanner.tsx`). `ChatMessageBubble.tsx` → `resources/js/components/chat-message-bubble/` (siblings: `BlockContent.tsx`, `LegacyContent.tsx`, `MarkdownContent.tsx`, `SentAtLabel.tsx`). `ChatHistoryPanel.tsx` → `resources/js/chat/pages/ai/chat-history-panel/` (siblings: `ChatHistoryListCard.tsx`, `ChatHistoryListItem.tsx`, `BotAccessCard.tsx`, `PromptNotesCard.tsx`).
*Alternative considered*: flat siblings directly in `resources/js/components/` (matching the `admin/pages/resume/targeted/` precedent). Rejected because the user explicitly asked for subdirectories, and `resources/js/components/` is a shared top-level library directory where flat siblings would blur which files belong to which parent component.

**2. Stateful/reusable logic → hooks in `resources/js/hooks/`, not colocated subdirectories.**
`useModelStatus` (status polling + warmup), `useChatStream` (SSE parsing, message state, send/stop), and `useChatUrlSync` (ChatBot's hash-redirect effect) go into the existing flat `resources/js/hooks/` directory, following the file-per-hook + JSDoc-comment convention already used by `useSessionExpiry.ts`.
*Alternative considered*: colocating hooks inside `chat-interface/`. Rejected because `useModelStatus` and the streaming logic are the most likely pieces to be reused by a future chat surface, and the codebase already has one canonical hooks location — splitting hooks across multiple directories would fragment that convention.

**3. `useChatStream` owns `messages`, `streamingBlocks`, `streamingToolPanels`, `isStreaming`, and the `sendMessage`/`stopStreaming` callbacks; `ChatInterface` keeps only UI-local state** (`messageText`, `initialTopMostItemIndex`) and wires the hook's outputs into the `Virtuoso` list and `ChatInputArea`.
*Alternative considered*: one giant `useChat` hook that also owns model status and session wiring. Rejected — model status and session-expiry are independent concerns with their own effects/lifecycles; merging them into one hook would just relocate the mixing problem rather than resolve it.

**4. Pure helpers (`getRelativeSentLabel`, `getLocaleDateTime`) move to `resources/js/utils/date.ts`**, joining the existing `formatCalendarDate` helper, rather than a new `chat`-scoped utils file.
*Alternative considered*: a new `resources/js/utils/chat.ts`. Rejected as unnecessary fragmentation — `utils/date.ts` already exists for exactly this kind of date-formatting helper and has no chat-specific coupling.

**5. Preserve the `ChatInterfaceHandle`/`forwardRef` + `useImperativeHandle` API exactly**, since `BuilderChatPanel.tsx` and `ChatBot.tsx` rely on the ref to trigger `sendMessage` from outside.

## Risks / Trade-offs

- [Extracting `sendMessage` into `useChatStream` could subtly change closure timing for the refs it captures (`streamingRafRef`, `abortControllerRef`, `extraPayloadRef`)] → Move refs into the hook together with the callback in one step per file; verify with a manual smoke test of a live streamed response (including a mid-stream tool-use panel) after each extraction, not just a type-check.
- [Splitting `ChatMessageBubble.tsx` render paths could break the `react-virtuoso` item recycling if new components aren't memoized consistently] → Wrap extracted leaf components (`BlockContent`, `LegacyContent`) in `React.memo` only if they don't already re-render correctly; verify visually in the browser with a long scrollback, not just unit-level checks.
- [Consumers importing symbols directly from `ChatInterface.tsx` or `ChatMessageBubble.tsx` (e.g. `BuilderChatPanel.tsx` imports the `ChatMessage` type) could break if those types move] → Keep all currently-exported types (`ChatMessage`, `ModelStatus`, `StreamEvent`, `ChatInterfaceProps`, `ChatInterfaceHandle`, `MessageBlock`) re-exported from the original top-level file, even if their definitions move into the subdirectory.
- [No existing test coverage for these components] → Rely on `npm run build` (TypeScript compilation) plus manual browser verification of all three consumer surfaces (`/ai/{bot}` chat page, admin targeted-resume builder chat panel, admin conversation read-only viewer) after the refactor, per CLAUDE.md's UI-verification guidance.

## Migration Plan

Refactor file-by-file in dependency order (leaf pure-helpers first, then hooks, then the components that use them), running `npm run build` and a manual smoke test after each file. No backend/deploy migration is involved — this is a pure frontend refactor with no data or route changes, so rollback is a plain `git revert` if an issue surfaces.
