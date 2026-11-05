## Context

See proposal.md for the problems. The relevant current structure:

- `ChatInterface` renders a `Card` holding `ChatVirtualList` (a Virtuoso list sized `calc(100dvh - 320px)`, 300 on phones, with a `messagePadding` footer), a `Divider`, `ModelStatusDisplay` (stacked MUI `Alert`s), `SessionExpiryBanner`, and `ChatInputArea`.
- `ChatInputArea` is a `form` with `position: absolute; bottom: 0`, so it floats over the list. Inside, a MUI `TextField multiline` has 44px of bottom padding, and Send/Stop and `DictationControl` are absolutely positioned at its bottom right. `DictationControl` also absolutely positions its own status caption at the bottom left of the text box.
- `useModelStatus` owns readiness (`modelStatus`, `isCheckingModelStatus`, `isWarmingModel`, `loadingMessage`). `useChatStream` owns the turn (`isStreaming`, `streamingBlocks`, `streamingToolPanels`, `error`) and writes server `status` frames into the same `loadingMessage`.
- `useDictation` (inside `DictationControl`) owns `state` (`idle | recording | transcribing`), `elapsedSeconds` and `message`.
- These components render under both the admin theme and the frozen public chat theme (`resources/js/chat/theme.ts`).

### The three directions

All three are drawn in Figma, file "Chat composer and status redesign": https://www.figma.com/design/lxAfK1g9d0gyq0m4ryDZao

| Frame | Node | What it shows |
| --- | --- | --- |
| 0. Today | `7:2` | The current screen with the three problems marked |
| A. The dock (recommended) | `2:2` | Screen, annotations, and all 14 composer states |
| A. The dock at 360px | `8:2` | Ready, streaming with a long draft, and a wrapped error at phone width |
| B. The margin and the rail | `4:2` | Screen, annotations, four key states |
| C. Status in the reply | `5:2` | Screen, annotations, four key states |

**A. The dock.** One bordered surface, two rows: text on top, a 44px toolbar underneath. The toolbar's left side is the status readout, a sentence led by a 3×14px notch, the device the admin sidebar uses to mark the current page. Right side: Microphone, then Send (filled primary) or Stop (outlined primary). Ready is a blue notch and muted words. Work under way is an orange notch that eases between half and full height. Warnings and errors use their colour and wrap. Session expiry swaps the buttons for Refresh.

**B. The margin and the rail.** A one-row composer whose buttons sit in a right-hand margin behind a hairline, so the text column ends where they begin. Status is a 3px rail along the composer's top edge (blue still, orange travelling segment while working, red for errors) plus a caption line underneath for the words. Most compact, but the rail is easy to miss and the caption carries all the meaning, so it rebuilds the progress bar in a new place.

**C. Status in the reply.** The dock's layout without a readout. Readiness becomes a system line at the foot of the transcript; the live reply carries a heading (Thinking, Running a tool, Writing) and, when done, a record line ("Thought for 6 seconds, ran read_resume"). Status sits where the user reads, but adds items to the virtualized list, needs durations the chat doesn't store today, and still needs the composer for errors, expiry and dictation, so status ends up in two places.

The developer picks the direction before `/opsx:apply`. The rest of this document, the specs and the tasks describe **A**.

## Goals / Non-Goals

**Goals:**

- Fix both overlap bugs structurally (layout, not padding values), so they can't come back when a control is added.
- Put every status the chat reports in one place with one visual language.
- Keep the one bold element quiet: the notch is small, and moves only while work is under way.

**Non-Goals:**

- No change to what the server sends, to `useModelStatus`'s or `useChatStream`'s behaviour, or to the turn lifecycle.
- No restyle of message bubbles, tool panels, reasoning panels, the chat tabs, or the status badge on the public chat's tab (`statusToBadgeColor` keeps driving it through `onModelStatusChange`).
- No addition to `/admin/design-reference`: the composer lives in `resources/js/components/`, not the admin's shared components.
- No retry button for a failed reply. The readout says to send again, which is what works today.

## Decisions

### 1. The composer is a hand-built surface around `InputBase`, not a `TextField`

The toolbar has to sit inside the composer's border. A `TextField`'s outline is drawn by its own `NotchedOutline` around the input only, and multiline adornments sit beside the text rather than below it. So the surface becomes a `Box` with the border and radius, containing `InputBase multiline` (the same `TextareaAutosize` underneath, so `maxRows` and the height transition keep working) and the toolbar `Box`. The focus ring is drawn with `:focus-within` on the surface (2px `primary.main`, compensating padding by 1px so text doesn't shift).

*Alternative:* keep `TextField` and fake the bottom row with padding. That is the current approach and the cause of the bug.

### 2. The card is a flex column; the list fills it

`Card` gets `display: flex; flex-direction: column` and the height the list used to have (`calc(100dvh - 320px)`, 300 on phones, with the same minimum). `ChatVirtualList` becomes `flex: 1 1 auto; min-height: 0` with Virtuoso at `height: 100%`. `aboveInput`, the composer dock and its slots are `flex: none` beneath it. `ChatInputArea` loses `position: absolute`; the dock gets a top hairline (`divider`) in place of the `Divider`.

`messagePadding` keeps its type and meaning (space under the last message) but its default drops from 40 to 16, and `ChatPanel` stops passing 140. Both numbers only existed to clear the floating composer.

*Alternative:* keep the overlay and grow the list's footer with the composer's measured height. Still an overlay, so anything that changes height (wrapping errors, beforeSend content) has to be measured too.

### 3. Pinning the transcript is explicit

Virtuoso's `followOutput` reacts to new items, not to its viewport shrinking, so a growing composer would push the newest message below the fold. `ChatVirtualList` records Virtuoso's `atBottomStateChange` in a ref. `ChatInterface` puts a `ResizeObserver` on the dock; when the dock's height changes and the list was at the bottom, it calls the Virtuoso handle's `scrollTo({ top: Number.MAX_SAFE_INTEGER })` (which includes the footer, unlike `scrollToIndex("LAST")`). When the list wasn't at the bottom it does nothing, so a reader's position is kept.

### 4. Status is resolved by one pure function

A new `resources/js/components/chat-interface/chatStatus.ts` exports `resolveChatStatus(input): ChatStatus`, where `ChatStatus` is `{ kind, tone, text, moving }`, and `tone` is `ready | idle | working | warning | error`. It implements the precedence in the `chat-status-readout` spec, minus dictation, from inputs `ChatInterface` already has: `isExpired`, `error`, `isStreaming`, `loadingMessage`, `streamingBlocks`, `streamingToolPanels`, `modelStatus`, `isCheckingModelStatus`, `isWarmingModel`, `isAuthenticated`.

`loadingMessage` means two things today. While `isStreaming` it is a server status frame; otherwise it is warm-up text. The function keeps that split, matching what `ModelStatusDisplay` showed.

"Newest thing streamed" uses the fact that `useChatStream` clears `streamingBlocks` whenever a tool call arrives: if there are blocks, the last block's type decides (text means writing, reasoning means thinking); if there are none and the last tool panel has no `output`, a tool is running; otherwise thinking. This stays correct for surfaces that never send tool result frames, where a panel's `output` stays undefined after text has started.

Keeping it pure means the wording and order live in one file that can be read against the spec, and the hooks are untouched (`chat-component-organization` still holds).

### 5. Dictation reports up; the composer merges it

`useDictation` stays inside `DictationControl`, because it needs `voice.config` and `DictationControl` only renders when `voice` is present (hooks can't be conditional in `ChatInputArea`). `DictationControl` drops its absolutely positioned caption and its absolute wrapper, renders only the button (or spinner), and gains `onStatusChange(status: ChatStatus | null)`, called from an effect when its state, elapsed time or message changes. `ChatInputArea` takes a new optional `status?: ChatStatus` prop from `ChatInterface` and shows the dictation status instead, unless the chat's status is `expired`. The spec's order (expiry, then dictation, then everything else) falls out of that.

Esc handling doesn't change: `DictationControl`'s capture-phase listener cancels a recording before `ChatInterface`'s key handler would stop a reply.

### 6. The readout component

`resources/js/components/chat-interface/StatusReadout.tsx` renders the notch and words. The notch is a 3×14px `Box` coloured by tone: `primary.main` (ready), `grey.400` (idle), `secondary.main` (working), `warning.main`, `error.main`. Recording uses an 8px `error.main` dot instead, as today. Words are `caption`-ish 13px `text.secondary`; warning words use `warning.dark` and error words `error.main` for contrast. `moving` applies an emotion `keyframes` animation on `transform: scaleY(0.5 → 1)`, 1.2s ease-in-out, alternating, disabled under `@media (prefers-reduced-motion: reduce)`.

Accessibility: the readout wrapper is `role="status"` with `aria-live="polite"`. For the error tone the words render inside a `role="alert"` element so the failure is announced assertively. Warning and error tones wrap (the toolbar aligns its buttons to the bottom); other tones are one line with an ellipsis.

All colours are palette names that both the admin theme and the frozen chat theme define. `grey.400` and `warning.dark` come from MUI's defaults in both.

### 7. Buttons

Send is a 36px `IconButton` with `primary.main` background and `primary.contrastText` icon, and `action.disabledBackground` when disabled. Stop is the same size, outlined in `primary.main`. Microphone is a plain 36px `IconButton`. Corner radius comes from `theme.shape.borderRadius`. Session expiry renders an outlined `Button` "Refresh" (`window.location.reload()`) in place of all three. The submit `button` keeps `type="submit"` so Enter-in-form behaviour is unchanged.

### 8. Phone cap

`ChatInputArea` reads `useMediaQuery(theme.breakpoints.down("sm"))` and uses `maxRows={isFocused ? (isNarrow ? 5 : 8) : 1}`. The rest of `chat-input-focus-resize` is untouched.

### 9. What gets deleted

`ModelStatusDisplay.tsx` and `chat-interface/SessionExpiryBanner.tsx` are removed once `ChatInterface` stops importing them (nothing else does). The `LinearProgress` blocks in `chat-message-bubble/BlockContent.tsx` and `LegacyContent.tsx` are removed, along with their `role="status"` wrappers, since the readout now announces streaming.

## Risks / Trade-offs

- [The readout is the only streaming indicator, and it sits under the transcript, not beside the reply] → It is always directly below the newest message because the transcript is pinned, and the Stop button is a second signal. Direction C is the alternative if this proves too far away.
- [`ResizeObserver` firing on every keystroke while the composer grows] → It only calls `scrollTo` when the list is at the bottom, and the height only changes when a line wraps.
- [`:focus-within` lights the surface when the microphone has focus, not just the text] → Acceptable, and arguably right: the surface is the control being used. Height collapse still keys off the text area's own focus.
- [Showing the model name could disclose provider details on the public chat] → The name only shows to signed-in viewers.
- [Two themes] → Only palette names both define are used; screenshots are taken under both (an admin chat and `/chats/<bot>`).
- [No JS test runner] → `resolveChatStatus` is pure and small enough to check by hand against the spec's table, and the tasks list the states to walk through in the browser.

## Migration Plan

Frontend only. Ships with the normal deploy (`npm run build`); no migrations, no queue restart needed for this change. Rollback is reverting the commit and rebuilding.
