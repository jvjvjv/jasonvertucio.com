## 1. Utility extraction (ChatMessageBubble)

- [x] 1.1 Move `getRelativeSentLabel` and `getLocaleDateTime` from `resources/js/components/ChatMessageBubble.tsx` into `resources/js/utils/date.ts`, updating call sites
- [x] 1.2 Run `npm run build` to confirm no type errors

## 2. Split ChatMessageBubble.tsx

- [x] 2.1 Create `resources/js/components/chat-message-bubble/MarkdownContent.tsx` for the shared `dangerouslySetInnerHTML`/`marked.parse` rendering used by both render paths (de-duplicating lines 183–192 and 287–294)
- [x] 2.2 Create `resources/js/components/chat-message-bubble/SentAtLabel.tsx` for the duplicated sent-time `Tooltip`/`Typography` snippet (de-duplicating lines 206–222 and 295–315)
- [x] 2.3 Create `resources/js/components/chat-message-bubble/BlockContent.tsx` for the block-based render path (lines 151–225), using `MarkdownContent` and `SentAtLabel`
- [x] 2.4 Create `resources/js/components/chat-message-bubble/LegacyContent.tsx` for the legacy single-content render path (lines 227–328), including the `wordWrap` toggle state and `handlePreDblClick`, using `MarkdownContent` and `SentAtLabel`
- [x] 2.5 Reduce `ChatMessageBubble.tsx` to selecting between `BlockContent` and `LegacyContent` based on `blocks` presence, keeping `MessageBlock` and other exported types in place
- [x] 2.6 Run `npm run build`; manually verify message rendering in the browser for both a block-based (streamed) message and a legacy historical message, including the double-click-to-toggle-wrap behavior

## 3. Extract chat hooks

- [x] 3.1 Create `resources/js/hooks/useModelStatus.ts`: status state, `updateModelStatus`/`setUnavailableStatus`, and the mount-time check/warmup effect from `ChatInterface.tsx` (lines 181–186, 242–259, 262–303), following the JSDoc-comment convention used in `useSessionExpiry.ts`
- [x] 3.2 Create `resources/js/hooks/useChatStream.ts`: `messages`, `streamingBlocks`, `streamingToolPanels`, `isStreaming`, `sendMessage`, `stopStreaming`, and their supporting refs (`streamingRafRef`, `abortControllerRef`, `extraPayloadRef`) from `ChatInterface.tsx` (lines 172–229, 305–534)
- [x] 3.3 Create `resources/js/hooks/useChatUrlSync.ts` for `ChatBot.tsx`'s post-first-message hash-redirect effect (lines 74–86)
- [x] 3.4 Run `npm run build` to confirm the new hooks type-check in isolation (consumers still reference the old inline logic at this point)

## 4. Wire ChatInterface.tsx to the new hooks and colocated sub-components

- [x] 4.1 Create `resources/js/components/chat-interface/EmptyPlaceholder.tsx` (lines 117–132)
- [x] 4.2 Create `resources/js/components/chat-interface/ChatVirtualList.tsx` for the `VirtualItem` assembly (lines 108–115, 558–581) and the `Virtuoso`/`itemContent` renderer (lines 600–697), accepting `messages`, `streamingBlocks`, `streamingToolPanels`, and `slots` as props
- [x] 4.3 Create `resources/js/components/chat-interface/SessionExpiryBanner.tsx` for the expired-session `Alert` block (lines 712–731)
- [x] 4.4 Update `ChatInterface.tsx` to consume `useModelStatus`, `useChatStream`, and the three colocated sub-components, keeping only `messageText`, `initialTopMostItemIndex`, `handleKeyDown`, the `useImperativeHandle` wiring, and the top-level JSX layout
- [x] 4.5 Re-export `ChatMessage`, `ModelStatus`, `StreamEvent`, `ChatInterfaceProps`, `ChatInterfaceHandle`, and `MessageBlock` from `ChatInterface.tsx` unchanged so existing imports keep working
- [x] 4.6 Run `npm run build`

## 5. Split ChatHistoryPanel.tsx

- [x] 5.1 Create `resources/js/chat/pages/ai/chat-history-panel/ChatHistoryListItem.tsx` for a single history row (lines 84–177)
- [x] 5.2 Create `resources/js/chat/pages/ai/chat-history-panel/ChatHistoryListCard.tsx` for the "Your Chats" card (lines 41–187), using `ChatHistoryListItem`
- [x] 5.3 Create `resources/js/chat/pages/ai/chat-history-panel/BotAccessCard.tsx` for the "Access" card (lines 188–213)
- [x] 5.4 Create `resources/js/chat/pages/ai/chat-history-panel/PromptNotesCard.tsx` for the "Prompt Notes" card (lines 215–225)
- [x] 5.5 Reduce `ChatHistoryPanel.tsx` to composing the three cards
- [x] 5.6 Run `npm run build`

## 6. Simplify ChatBot.tsx

- [x] 6.1 Extract the model-status-to-badge-color mapping (lines 173–183) into a small pure helper (e.g. `statusToBadgeColor`) colocated in `ChatBot.tsx`'s directory or inlined as a one-line util if trivial enough
- [x] 6.2 Extract `formatCost` (lines 96–97) into `resources/js/utils/` (or reuse an existing formatter if one already exists — check `resources/js/utils/` and `resources/js/admin/utils/` first)
- [x] 6.3 Replace the inline hash-redirect `useEffect` (lines 74–86) with the `useChatUrlSync` hook from task 3.3
- [x] 6.4 Run `npm run build`

## 7. Verification

- [x] 7.1 Start the dev server (`npm run dev` / `composer run dev`) and manually verify the public chat page (`/ai/{bot}`) end-to-end: sending a message, streaming a response, tool-use panel display, session-expiry banner (if testable), and the "Your Chats"/access/prompt-notes cards
- [x] 7.2 Manually verify the admin targeted-resume builder chat panel (`BuilderChatPanel.tsx`) still streams messages and triggers `page_reload` correctly
- [x] 7.3 Manually verify the admin conversation read-only viewer (`admin/pages/ai/conversations/Show.tsx`) still renders both block-based and legacy message bubbles correctly
- [x] 7.4 Run `npm run build` one final time to confirm a clean production build
