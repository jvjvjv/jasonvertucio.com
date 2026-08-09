## Why

The chat React components have grown too large and tangled to change safely: `ChatInterface.tsx` is 757 lines mixing SSE stream parsing, model-status polling, session-expiry wiring, and virtualized-list rendering in one file, and `ChatMessageBubble.tsx` (329 lines) duplicates markdown-rendering and timestamp-label JSX across its two render paths. This makes even small edits (e.g. tweaking the header title, as seen in the current uncommitted `ChatBot.tsx` diff) require reading through unrelated logic first. Breaking these into focused hooks and components will let changes be made confidently without re-deriving the whole file each time.

## What Changes

- Extract stream/session/model-status logic out of `ChatInterface.tsx` into reusable hooks in `resources/js/hooks/`: `useModelStatus`, `useChatStream` (SSE parsing + message state), and `useChatAutoStart`.
- Extract `ChatInterface.tsx`'s presentational sub-pieces (`EmptyPlaceholder`, the virtualized list item renderer, the session-expired banner) into a new `resources/js/components/chat-interface/` subdirectory, colocated with the component since they are single-use.
- Split `ChatMessageBubble.tsx`'s two rendering paths (block-based vs. legacy) into separate components under a new `resources/js/components/chat-message-bubble/` subdirectory, and de-duplicate the repeated markdown-render and "sent at" label JSX into shared sub-components within that directory.
- Move the timestamp-formatting helpers (`getRelativeSentLabel`, `getLocaleDateTime`) out of `ChatMessageBubble.tsx` into `resources/js/utils/date.ts`, alongside the existing `formatCalendarDate` helper.
- Split `ChatHistoryPanel.tsx`'s three unrelated cards (chat list, bot access info, prompt notes) into separate components under a new `resources/js/chat/pages/ai/chat-history-panel/` subdirectory.
- Extract `ChatBot.tsx`'s pure helper logic (`formatCost`, the model-status-to-badge-color mapping, the post-first-message URL-sync effect) into a `useChatUrlSync` hook and small util functions, reducing the page component to tab/identity-form orchestration.
- No behavior changes: props, rendered output, and public component APIs (`ChatInterfaceProps`, `ChatInterfaceHandle`, `ChatMessage`, `ModelStatus`, `MessageBlock`) stay the same so existing consumers (`ChatBot.tsx`, `BuilderChatPanel.tsx`, `admin/pages/ai/conversations/Show.tsx`) require no changes beyond import path updates for anything they import directly from the split files.

## Capabilities

### New Capabilities
- `chat-component-organization`: Establishes the directory/hook structure for decomposing large chat React components into focused, single-responsibility pieces, and the pattern to follow for future chat UI work.

### Modified Capabilities
(none — this is a pure internal refactor; no existing spec covers behavioral requirements for the chat UI today, and no user-facing behavior changes)

## Impact

- **Affected files**: `resources/js/components/ChatInterface.tsx`, `resources/js/components/ChatMessageBubble.tsx`, `resources/js/chat/pages/ai/ChatBot.tsx`, `resources/js/chat/pages/ai/ChatHistoryPanel.tsx`, plus new files under `resources/js/hooks/`, `resources/js/components/chat-interface/`, `resources/js/components/chat-message-bubble/`, `resources/js/chat/pages/ai/chat-history-panel/`, and `resources/js/utils/date.ts`.
- **Indirectly touched (import-path only, no logic change)**: `resources/js/admin/pages/resume/targeted/BuilderChatPanel.tsx`, `resources/js/admin/pages/resume/targeted/Show.tsx` (type-only import), `resources/js/admin/pages/ai/conversations/Show.tsx`.
- **Out of scope**: `resources/js/admin/pages/resume/targeted/Show.tsx` itself (719 lines) is not touched beyond any necessary import updates — its bulk is unrelated status/CRUD logic, not chat UI, and it already follows the codebase's flat-sibling-file convention.
- **No backend, database, or route changes.**
- **No new dependencies.**
