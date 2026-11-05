## 0. Approval

- [x] 0.1 Confirm the developer has approved Direction B in the Figma file (https://www.figma.com/design/lxAfK1g9d0gyq0m4ryDZao, frame `4:2`). Verify: the approval is stated in the conversation.
- [x] 0.2 Create a feature branch off `develop` (`feature/redesign-chat-composer-and-status`). Verify: `git branch --show-current` names it.

## 1. Status resolution

- [x] 1.1 Add `resources/js/components/chat-interface/chatStatus.ts` exporting the `ChatStatus` type (`kind`, `tone`, `text`, `moving`) and `resolveChatStatus()`, implementing the precedence and wording in `specs/chat-status-readout/spec.md` (minus dictation) as described in design decision 4, with the ready state's `text` being the model name for signed-in viewers and empty otherwise. Verify: walk every numbered state in the spec through the function by reading it side by side, including the server-status-frame, tool-without-result-then-text, guest-vs-signed-in ready, and expired-outranks-everything scenarios; `npx tsc --noEmit` passes.
- [x] 1.2 Add `resources/js/components/chat-interface/StatusRail.tsx`: the 3px `aria-hidden` rail, full-width colour per tone or a `divider` track with a quarter-width `secondary.main` segment for `working`, travelling left to right with `translateX` keyframes when `moving`, parked in the middle when not moving and under `prefers-reduced-motion`, no absolute positioning (design decision 6). Verify: ESLint passes on the file and no colour literal appears in it.
- [x] 1.3 Add `resources/js/components/chat-interface/StatusCaption.tsx`: the caption line with the words on the left in a polite `role="status"` element (errors inside `role="alert"`), the recording dot, visually hidden "Ready" / "Ready on " text for the ready state, wrapping for warning/error and ellipsis otherwise, and a right-hand slot for the keyboard hint (design decision 6). Verify: ESLint passes on the file and no colour literal appears in it.

## 2. Dictation reports its state

- [x] 2.1 Change `resources/js/components/voice/DictationControl.tsx` to render only the button or spinner, with no absolute positioning, and to report recording, transcribing and problem states through a new `onStatusChange(status: ChatStatus | null)` prop (design decision 5). Keep the capture-phase Esc listener and the tooltip. Verify: as a `use-voice` holder, recording shows the clock in the caption line and a travelling rail, Esc cancels, and a denied microphone shows "Microphone access was denied." in the caption with a red rail.

## 3. The composer

- [x] 3.1 Rebuild `resources/js/components/ChatInputArea.tsx` as the one-row surface: `StatusRail` across the top, then a row of `InputBase multiline` (text column) and the action margin behind a `divider` hairline holding Microphone, then Send or Stop, aligned to the bottom; `StatusCaption` beneath the surface; `:focus-within` ring; no `position: absolute` anywhere in the form (design decisions 1, 6, 7, 10). Add the optional `status?: ChatStatus` prop and merge dictation status over it unless it is `expired`; render Refresh in the margin in place of the buttons when expired. Keep `ChatInputAreaProps`' existing fields, the `beforeSend` slot above the surface and the `afterSend` caption below the caption line, rich paste, transcript insertion at the caret, and the height transition. Verify: with a 20-line draft focused and scrolled to the end, no text is behind any button and the buttons sit at the bottom of the margin; the surface collapses to one line on blur and grows on focus, animated.
- [x] 3.2 Cap focused growth at five lines below the `sm` breakpoint and eight above (design decision 9). Verify: pasting 20 lines at 360px stops at five lines, at 1280px stops at eight.
- [x] 3.3 Show the keyboard hint on the caption's right in the `ready` and `idle` tones at or above `sm`: "⌘+Enter sends" on Apple platforms, "Ctrl+Enter sends" otherwise (design decision 8). Verify: the hint shows when ready at 1280px, is gone while a reply streams, after a failure, and at 360px; with the platform emulated as macOS it reads "⌘+Enter sends".

## 4. The chat card

- [x] 4.1 In `resources/js/components/ChatInterface.tsx`, make the `Card` a fixed-height flex column (the height formula moves here from the list), put `aboveInput` and the dock beneath the list as `flex: none`, compute `resolveChatStatus()` and pass it to `ChatInputArea`, and remove `ModelStatusDisplay`, `SessionExpiryBanner` and the `Divider` (design decision 2). Keep `ChatInterfaceProps`, `ChatInterfaceHandle` and every exported type unchanged and lower the `messagePadding` default to 16. Verify: `npx tsc --noEmit` passes; growing the draft shortens the transcript and covers nothing.
- [x] 4.2 In `resources/js/components/chat-interface/ChatVirtualList.tsx`, fill the parent (`flex: 1 1 auto; min-height: 0`, Virtuoso at `height: 100%`) instead of the `100dvh` calc, and expose at-bottom state; in `ChatInterface`, observe the dock's size and scroll the list to the end on resize only when it was at the bottom (design decision 3). Verify: at the bottom, typing until the composer grows keeps the newest message fully visible, and so does a failure whose caption wraps; scrolled up to an earlier message, typing leaves that message in view.
- [x] 4.3 Remove `messagePadding={140}` from `resources/js/admin/pages/employment/applications/ChatPanel.tsx`. Verify: the application chat shows no blank band under the last message.
- [x] 4.4 Size the chat card from its measured top (`useCardTop`) so it fills the viewport below each page's chrome instead of the fixed `100dvh - 320px` (design decision 2). Verify: on the public chat at 1280 and 360 pixels the card's bottom meets the window's bottom and the document does not scroll; the application chat no longer ends 113px short.

## 5. Remove the old status UI

- [x] 5.1 Remove the streaming `LinearProgress` blocks (and their `role="status"` wrappers) from `resources/js/components/chat-message-bubble/BlockContent.tsx` and `LegacyContent.tsx`. Verify: a streaming reply shows no progress bar, and the caption says Thinking, Running a tool, or Writing as the turn progresses while the rail's segment travels.
- [x] 5.2 Delete `resources/js/components/ModelStatusDisplay.tsx` and `resources/js/components/chat-interface/SessionExpiryBanner.tsx`. Verify: `grep -rn "ModelStatusDisplay\|SessionExpiryBanner" resources/js` returns nothing and `npm run build` succeeds.

## 6. Docs

- [x] 6.1 Update the *Dictation* bullet in `.claude/context/voice.md` to say the recording state now shows in the composer's status caption and rail through `onStatusChange`, rather than as a caption drawn by `DictationControl` over the text box. Verify: the paragraph matches the code.

## 7. Verification

- [x] 7.1 Run ESLint on every changed file under `resources/js`, `npx tsc --noEmit`, and `npm run build`, and fix Prettier issues in the files this change touched before committing (husky lints `resources/js`). Verify: all pass.
- [x] 7.2 In the browser, walk the readout through every state in `specs/chat-status-readout/spec.md`: ready (as guest and signed in), checking, waking, warm-up failed, provider unavailable, thinking, running a tool, writing, reply failed, recording, transcribing, session expired. Use the application chat, company-intel chat and a public `/chats/<bot>` page so both themes are covered. Verify: the four states drawn in Figma frame `4:2` (ready, waking, long draft focused, reply failed) match it, the others match the spec's wording and the design's rail table, and reduced motion is checked once through the browser's emulation (segment parked in the middle).
- [x] 7.3 Take screenshots at 1280 and 360 pixels of the application chat and a public chat (ready, streaming with a long draft, failed reply), check `Tab` focus rings on the text area, Microphone, Send and Stop, and show them to the developer before merging. Verify: the developer has seen and accepted them.
