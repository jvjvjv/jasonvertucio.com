## Why

The chat screen's composer is still messy in three ways. Microphone and Send are absolutely positioned over the text box, so a long draft scrolls underneath them and the caret can sit behind a button. The composer form itself is absolutely positioned over the bottom of the transcript, so a draft that grows past one line covers the newest messages. And model readiness is reported as a stack of full-width MUI alerts, including a green "Model is ready." that stays for the whole session, alongside a separate progress bar inside the streaming bubble.

Three design directions were drawn in Figma (file "Chat composer and status redesign", https://www.figma.com/design/lxAfK1g9d0gyq0m4ryDZao). The developer chose **Direction B, the margin and the rail** (frame `4:2`) on 2026-11-05, and this change plans it.

## What Changes

- The composer stays one row inside one bordered surface: the text column, then an action margin on its right, separated by a hairline, holding Microphone and Send or Stop. The margin is its own flex child, so text wraps at its edge and can never run under a button. The margin's buttons sit at the bottom of the row as the text grows.
- A 3px **status rail** runs along the composer's top edge. Its colour says the state: blue and still when ready, a short orange segment travelling left to right while work is under way (waking the model, thinking, running a tool, writing, recording, transcribing), and warning orange or red and still when something needs attention.
- A **caption line** under the composer carries the words, because the rail cannot explain itself: checking the model, waking the model, thinking, running a named tool, writing the reply, recording, transcribing, warnings, errors, provider unavailable, session expired. Ready is quiet: the caption shows only the model's name (signed-in viewers) or nothing (guests), and a keyboard hint ("Ctrl+Enter sends", or "⌘+Enter sends" on Apple platforms) sits at its right.
- These replace `ModelStatusDisplay`'s stacked alerts, the separate session-expiry banner, and the dictation status line laid over the text box. The `LinearProgress` bar inside the streaming assistant bubble is removed; the rail and caption report that the reply is streaming.
- Under `prefers-reduced-motion` the travelling segment stops in the middle of the rail.
- The chat card becomes a fixed-height column: the transcript fills the space the composer does not use. The composer is in normal flow, so a growing draft shortens the transcript instead of covering it. A transcript that was showing its newest message stays pinned to it as the composer grows or shrinks; a transcript the user had scrolled up in keeps its place.
- While a reply streams, Send becomes an outlined Stop in the same place, and the caption says that Esc stops it. Session expiry puts a Refresh button in the margin in place of Microphone and Send.
- On narrow screens (below the `sm` breakpoint) the composer grows to at most five lines instead of eight, and the keyboard hint is hidden.
- Unchanged: Ctrl/Cmd+Enter sends, Esc stops a turn or cancels a recording, collapse to one line on blur and animated height changes, rich paste, dictation inserting at the caret, the `beforeSend` and `afterSend` slots, and every exported prop, handle and type of `ChatInterface` and `ChatInputArea`.

## Capabilities

### New Capabilities

- `chat-composer-layout`: the composer's one-row structure (text column and action margin, text never under controls), its place in the chat card's column (never over the transcript), and how the transcript stays pinned to its newest message while the composer resizes.
- `chat-status-readout`: the single status readout, a rail on the composer's top edge plus a caption line beneath it, that reports model readiness, turn progress, dictation, warnings, errors and session expiry, replacing the alert stack, expiry banner and in-bubble progress bar.

### Modified Capabilities

None. `chat-input-focus-resize` (collapse on blur, grow while focused up to a configured cap, animated height) and `admin-voice` (dictation inserts at the cursor, composer reports dictation problems) keep their requirements as written; this change satisfies them in the new layout. `chat-component-organization`'s requirements still hold: status logic stays in `useModelStatus` and `useChatStream`, and public APIs are preserved.

## Impact

- **Components:** `resources/js/components/ChatInputArea.tsx` (restructured), `ChatInterface.tsx` (column layout, readout state), `chat-interface/ChatVirtualList.tsx` (fills its parent instead of a `100dvh` calc; stays pinned to the end on resize), `voice/DictationControl.tsx` (no longer absolutely positioned; reports its state up instead of drawing its own status line), `chat-message-bubble/BlockContent.tsx` and `LegacyContent.tsx` (progress bar removed). `ModelStatusDisplay.tsx` and `chat-interface/SessionExpiryBanner.tsx` are replaced by new colocated `chat-interface/StatusRail.tsx` and `chat-interface/StatusCaption.tsx` and deleted once nothing imports them.
- **Consumers:** `admin/pages/employment/applications/ChatPanel.tsx`, `admin/pages/employment/company-intel/Show.tsx` and `chat/pages/ai/ChatBot.tsx` keep working with the props they pass today. `ChatPanel` drops its `messagePadding={140}`, which only existed to clear the floating composer and would otherwise leave 140px of blank space. Page chrome above the card is unchanged; the card now fills the viewport below that chrome, measured rather than guessed with a fixed `320px`.
- **Both bundles:** these components render under the admin theme and the frozen public chat theme, so colours come from palette names both themes define (`primary`, `secondary`, `warning`, `error`, `text.secondary`, `divider`, `background.paper`), never literals.
- **No backend, route, API, dependency or database changes.** No PHP tests are affected. There is no JS test runner, so verification is `npm run build`, ESLint, `tsc`, and screenshots at 1280 and 360 pixels shown to the developer before merging.
