## 0. Approval

- [ ] 0.1 Confirm the developer has approved Direction A in the Figma file (https://www.figma.com/design/lxAfK1g9d0gyq0m4ryDZao, frames `2:2` and `8:2`). If they chose B or C, stop and revise these artifacts with `/opsx:update` before writing code. Verify: the approval is stated in the conversation.
- [ ] 0.2 Create a feature branch off `develop` (`feature/redesign-chat-composer-and-status`). Verify: `git branch --show-current` names it.

## 1. Status resolution

- [ ] 1.1 Add `resources/js/components/chat-interface/chatStatus.ts` exporting the `ChatStatus` type (`kind`, `tone`, `text`, `moving`) and `resolveChatStatus()`, implementing the precedence and wording in `specs/chat-status-readout/spec.md` (minus dictation) as described in design decision 4. Verify: walk every numbered state in the spec through the function by reading it side by side, including the server-status-frame, tool-without-result-then-text, guest-vs-signed-in ready, and expired-outranks-everything scenarios; `npx tsc --noEmit` passes.
- [ ] 1.2 Add `resources/js/components/chat-interface/StatusReadout.tsx`: notch (or recording dot), words, tone colours from palette names only, `scaleY` keyframes when `moving`, disabled under `prefers-reduced-motion`, polite `role="status"` wrapper with errors inside `role="alert"`, wrap for warning/error and ellipsis otherwise (design decision 6). Verify: ESLint passes on the file and no colour literal appears in it.

## 2. Dictation reports its state

- [ ] 2.1 Change `resources/js/components/voice/DictationControl.tsx` to render only the button or spinner, with no absolute positioning, and to report recording, transcribing and problem states through a new `onStatusChange(status: ChatStatus | null)` prop (design decision 5). Keep the capture-phase Esc listener and the tooltip. Verify: as a `use-voice` holder, recording shows the clock in the composer's readout, Esc cancels, and a denied microphone shows "Microphone access was denied." in the readout.

## 3. The composer

- [ ] 3.1 Rebuild `resources/js/components/ChatInputArea.tsx` as the two-row surface: `InputBase multiline` on top, toolbar row below with `StatusReadout` (left), Microphone, then Send or Stop (right); `:focus-within` ring; no `position: absolute` anywhere in the form (design decisions 1, 7). Add the optional `status?: ChatStatus` prop and merge dictation status over it unless it is `expired`; render Refresh in place of the buttons when expired. Keep `ChatInputAreaProps`' existing fields, the `beforeSend` slot above the surface and the `afterSend` caption below it, rich paste, transcript insertion at the caret, and the height transition. Verify: with a 20-line draft focused and scrolled to the end, no text is behind any button; the surface collapses to one line on blur and grows on focus, animated.
- [ ] 3.2 Cap focused growth at five lines below the `sm` breakpoint and eight above (design decision 8). Verify: pasting 20 lines at 360px stops at five lines, at 1280px stops at eight.

## 4. The chat card

- [ ] 4.1 In `resources/js/components/ChatInterface.tsx`, make the `Card` a fixed-height flex column (the height formula moves here from the list), put `aboveInput` and the dock beneath the list as `flex: none`, compute `resolveChatStatus()` and pass it to `ChatInputArea`, and remove `ModelStatusDisplay`, `SessionExpiryBanner` and the `Divider` (design decision 2). Keep `ChatInterfaceProps`, `ChatInterfaceHandle` and every exported type unchanged and lower the `messagePadding` default to 16. Verify: `npx tsc --noEmit` passes; growing the draft shortens the transcript and covers nothing.
- [ ] 4.2 In `resources/js/components/chat-interface/ChatVirtualList.tsx`, fill the parent (`flex: 1 1 auto; min-height: 0`, Virtuoso at `height: 100%`) instead of the `100dvh` calc, and expose at-bottom state; in `ChatInterface`, observe the dock's size and scroll the list to the end on resize only when it was at the bottom (design decision 3). Verify: at the bottom, typing until the composer grows keeps the newest message fully visible; scrolled up to an earlier message, typing leaves that message in view.
- [ ] 4.3 Remove `messagePadding={140}` from `resources/js/admin/pages/resume/applications/ChatPanel.tsx`. Verify: the application chat shows no blank band under the last message.

## 5. Remove the old status UI

- [ ] 5.1 Remove the streaming `LinearProgress` blocks (and their `role="status"` wrappers) from `resources/js/components/chat-message-bubble/BlockContent.tsx` and `LegacyContent.tsx`. Verify: a streaming reply shows no progress bar, and the readout says Thinking, Running a tool, or Writing as the turn progresses.
- [ ] 5.2 Delete `resources/js/components/ModelStatusDisplay.tsx` and `resources/js/components/chat-interface/SessionExpiryBanner.tsx`. Verify: `grep -rn "ModelStatusDisplay\|SessionExpiryBanner" resources/js` returns nothing and `npm run build` succeeds.

## 6. Docs

- [ ] 6.1 Update the *Dictation* bullet in `.claude/context/voice.md` to say the recording state now shows in the composer's status readout through `onStatusChange`, rather than as a caption drawn by `DictationControl`. Verify: the paragraph matches the code.

## 7. Verification

- [ ] 7.1 Run ESLint on every changed file under `resources/js`, `npx tsc --noEmit`, and `npm run build`, and fix Prettier issues in the files this change touched before committing (husky lints `resources/js`). Verify: all pass.
- [ ] 7.2 In the browser, walk the readout through every state on the Figma `2:2` sheet: ready (as guest and signed in), checking, waking, warm-up failed, provider unavailable, thinking, running a tool, writing, reply failed, recording, transcribing, session expired. Use the application chat, company-intel chat and a public `/chats/<bot>` page so both themes are covered. Verify: each matches the frame's wording and colour, with reduced motion checked once through the browser's emulation.
- [ ] 7.3 Take screenshots at 1280 and 360 pixels of the application chat and a public chat (ready, streaming with a long draft, failed reply), check `Tab` focus rings on the text area, Microphone, Send and Stop, and show them to the developer before merging. Verify: the developer has seen and accepted them.
