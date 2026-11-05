## Why

The chat screen's composer is still messy in three ways. Microphone and Send are absolutely positioned over the text box, so a long draft scrolls underneath them and the caret can sit behind a button. The composer form itself is absolutely positioned over the bottom of the transcript, so a draft that grows past one line covers the newest messages. And model readiness is reported as a stack of full-width MUI alerts, including a green "Model is ready." that stays for the whole session, alongside a separate progress bar inside the streaming bubble.

Three design directions were drawn in Figma (file "Chat composer and status redesign", https://www.figma.com/design/lxAfK1g9d0gyq0m4ryDZao). This change plans **Direction A, the dock**, which is the recommended one. **The developer approves the direction before `/opsx:apply`.** If B or C is chosen instead, the artifacts are revised with `/opsx:update` first.

## What Changes

- The composer becomes one bordered surface with two rows: the text area on top, and a toolbar row underneath holding the status readout (left) and Microphone and Send or Stop (right). No control is positioned over the text, so typed text can never run under a button.
- The chat card becomes a fixed-height column: the transcript fills the space the composer does not use. The composer is in normal flow, so a growing draft shortens the transcript instead of covering it. A transcript that was showing its newest message stays pinned to it as the composer grows or shrinks; a transcript the user had scrolled up in keeps its place.
- A single status readout replaces `ModelStatusDisplay`'s stacked alerts, the separate session-expiry banner, and the dictation status line laid over the text box. It is a short sentence led by a small coloured notch (the device the admin sidebar uses for the current page): checking the model, waking the model, thinking, running a named tool, writing the reply, recording, transcribing, warnings, errors, provider unavailable, session expired. Ready is quiet: a blue notch and the model's name, and no green alert.
- The notch moves (eases between half and full height) only while work is under way, and holds still under `prefers-reduced-motion`.
- The `LinearProgress` bar inside the streaming assistant bubble is removed; the readout reports that the reply is streaming.
- While a reply streams, Send becomes an outlined Stop in the same place, and the readout says that Esc stops it. Session expiry puts a Refresh button where the buttons were.
- On narrow screens (below the `sm` breakpoint) the composer grows to at most five lines instead of eight.
- Unchanged: Ctrl/Cmd+Enter sends, Esc stops a turn or cancels a recording, collapse to one line on blur and animated height changes, rich paste, dictation inserting at the caret, the `beforeSend` and `afterSend` slots, and every exported prop, handle and type of `ChatInterface` and `ChatInputArea`.

## Capabilities

### New Capabilities

- `chat-composer-layout`: the composer's two-row structure (text never under controls), its place in the chat card's column (never over the transcript), and how the transcript stays pinned to its newest message while the composer resizes.
- `chat-status-readout`: the single in-composer status readout that reports model readiness, turn progress, dictation, warnings, errors and session expiry in words, replacing the alert stack, expiry banner and in-bubble progress bar.

### Modified Capabilities

None. `chat-input-focus-resize` (collapse on blur, grow while focused up to a configured cap, animated height) and `admin-voice` (dictation inserts at the cursor, composer reports dictation problems) keep their requirements as written; this change satisfies them in the new layout. `chat-component-organization`'s requirements still hold: status logic stays in `useModelStatus` and `useChatStream`, and public APIs are preserved.

## Impact

- **Components:** `resources/js/components/ChatInputArea.tsx` (restructured), `ChatInterface.tsx` (column layout, readout state), `chat-interface/ChatVirtualList.tsx` (fills its parent instead of a `100dvh` calc; stays pinned to the end on resize), `voice/DictationControl.tsx` (no longer absolutely positioned; reports its state up instead of drawing its own status line), `chat-message-bubble/BlockContent.tsx` and `LegacyContent.tsx` (progress bar removed). `ModelStatusDisplay.tsx` and `chat-interface/SessionExpiryBanner.tsx` are replaced by a new colocated `chat-interface/StatusReadout.tsx` and deleted once nothing imports them.
- **Consumers:** `admin/pages/resume/applications/ChatPanel.tsx`, `admin/pages/employment/company-intel/Show.tsx` and `chat/pages/ai/ChatBot.tsx` keep working with the props they pass today. `ChatPanel` drops its `messagePadding={140}`, which only existed to clear the floating composer and would otherwise leave 140px of blank space. Page chrome above the card is unchanged; the card height formula moves from the list to the card.
- **Both bundles:** these components render under the admin theme and the frozen public chat theme, so colours come from palette names both themes define (`primary`, `secondary`, `warning`, `error`, `text.secondary`, `divider`), never literals.
- **No backend, route, API, dependency or database changes.** No PHP tests are affected. There is no JS test runner, so verification is `npm run build`, ESLint, `tsc`, and screenshots at 1280 and 360 pixels shown to the developer before merging.
