## Context

See proposal.md for the problems. The relevant current structure:

- `ChatInterface` renders a `Card` holding `ChatVirtualList` (a Virtuoso list sized `calc(100dvh - 320px)`, 300 on phones, with a `messagePadding` footer), a `Divider`, `ModelStatusDisplay` (stacked MUI `Alert`s), `SessionExpiryBanner`, and `ChatInputArea`.
- `ChatInputArea` is a `form` with `position: absolute; bottom: 0`, so it floats over the list. Inside, a MUI `TextField multiline` has 44px of bottom padding, and Send/Stop and `DictationControl` are absolutely positioned at its bottom right. `DictationControl` also absolutely positions its own status caption at the bottom left of the text box. The `afterSend` slot renders as a right-aligned caption under the input.
- `useModelStatus` owns readiness (`modelStatus`, `isCheckingModelStatus`, `isWarmingModel`, `loadingMessage`). `useChatStream` owns the turn (`isStreaming`, `streamingBlocks`, `streamingToolPanels`, `error`) and writes server `status` frames into the same `loadingMessage`.
- `useDictation` (inside `DictationControl`) owns `state` (`idle | recording | transcribing`), `elapsedSeconds` and `message`.
- `ChatInterface`'s key handler sends on Enter with `ctrlKey` or `metaKey`. Nothing on screen says so today.
- These components render under both the admin theme and the frozen public chat theme (`resources/js/chat/theme.ts`).

### The three directions

All three are drawn in Figma, file "Chat composer and status redesign": https://www.figma.com/design/lxAfK1g9d0gyq0m4ryDZao

| Frame | Node | What it shows |
| --- | --- | --- |
| 0. Today | `7:2` | The current screen with the three problems marked |
| A. The dock | `2:2` | Screen, annotations, and all 14 composer states |
| A. The dock at 360px | `8:2` | Ready, streaming with a long draft, and a wrapped error at phone width |
| B. The margin and the rail (chosen) | `4:2` | Screen, annotations, four key states |
| C. Status in the reply | `5:2` | Screen, annotations, four key states |

**A. The dock.** One bordered surface, two rows: text on top, a 44px toolbar underneath holding a notch-led status sentence and the buttons. Not chosen.

**B. The margin and the rail.** A one-row composer whose buttons sit in a right-hand margin behind a hairline, so the text column ends where they begin. Status is a 3px rail along the composer's top edge (blue still, orange travelling segment while working, warning or red still when something needs attention) plus a caption line underneath for the words and a keyboard hint. The most compact of the three when collapsed.

**C. Status in the reply.** The dock's layout with readiness and progress moved into the transcript. Not chosen.

The developer chose **B** on 2026-11-05. The rest of this document, the specs and the tasks describe B. Frame `4:2` draws four states (ready, waking, long draft focused, reply failed) and the streaming screen; the remaining states follow the rules below and have no frame of their own.

## Goals / Non-Goals

**Goals:**

- Fix both overlap bugs structurally (layout, not padding values), so they can't come back when a control is added.
- Put every status the chat reports in one place with one visual language.
- Keep the composer compact: one line tall when collapsed, with motion only while work is under way.

**Non-Goals:**

- No change to what the server sends, to `useModelStatus`'s or `useChatStream`'s behaviour, or to the turn lifecycle.
- No restyle of message bubbles, tool panels, reasoning panels, the chat tabs, or the status badge on the public chat's tab (`statusToBadgeColor` keeps driving it through `onModelStatusChange`).
- No addition to `/admin/design-reference`: the composer lives in `resources/js/components/`, not the admin's shared components.
- No retry button for a failed reply. The caption says to send again, which is what works today.

## Decisions

### 1. The composer is a hand-built surface around `InputBase`, not a `TextField`

The action margin has to sit inside the composer's border, beside the text, with the rail above both. A `TextField`'s outline is drawn by its own `NotchedOutline` around the input only. So the surface becomes a `Box` (`background.paper`, 1px `divider` border, `theme.shape.borderRadius`, `overflow: hidden`) laid out as a column:

1. `StatusRail`, 3px tall, full width.
2. A row (`display: flex; align-items: stretch`):
   - `InputBase multiline` with `flex: 1 1 auto; min-width: 0` and 14px padding (the same `TextareaAutosize` underneath, so `maxRows` and the height transition keep working; its scrollbar stays inside the text column).
   - The action margin, `flex: none`, with a 1px `divider` left border, `display: flex; align-items: flex-end`, holding Microphone and Send or Stop, so the buttons stay at the bottom as the text grows.

The focus ring is drawn with `:focus-within` on the surface (2px `primary.main`, compensating padding by 1px so text doesn't shift). Nothing in the form uses `position: absolute`.

*Alternative:* keep `TextField` and fake the margin with right padding. That is the current approach and the cause of the bug.

### 2. The card is a flex column; the list fills it

`Card` gets `display: flex; flex-direction: column` and fills the viewport below wherever the page's chrome leaves it: `calc(100dvh - <card top>px)`, where `useCardTop` (colocated in `chat-interface/`) measures the card's offset from the top of the document, re-measuring on window resize, when the page's content changes height, and when a hidden tab panel holding the card is shown. Until it has measured, the old `320px` (300 on phones) stands in. The minimum height stays. The fixed `320px` guessed at each page's chrome and left the admin application chat 113px short of the bottom of the window; measuring fits every consumer without a per-page number. `ChatVirtualList` becomes `flex: 1 1 auto; min-height: 0` with Virtuoso at `height: 100%`. `aboveInput`, the composer dock and its slots are `flex: none` beneath it. `ChatInputArea` loses `position: absolute`; the dock gets a top hairline (`divider`) in place of the `Divider`.

`messagePadding` keeps its type and meaning (space under the last message) but its default drops from 40 to 16, and `ChatPanel` stops passing 140. Both numbers only existed to clear the floating composer.

*Alternative:* keep the overlay and grow the list's footer with the composer's measured height. Still an overlay, so anything that changes height (wrapping errors, beforeSend content) has to be measured too.

### 3. Pinning the transcript is explicit

Virtuoso's `followOutput` reacts to new items, not to its viewport shrinking, so a growing composer would push the newest message below the fold. `ChatVirtualList` records Virtuoso's `atBottomStateChange` in a ref. `ChatInterface` puts a `ResizeObserver` on the dock; when the dock's height changes and the list was at the bottom, it calls the Virtuoso handle's `scrollTo({ top: Number.MAX_SAFE_INTEGER })` (which includes the footer, unlike `scrollToIndex("LAST")`). When the list wasn't at the bottom it does nothing, so a reader's position is kept. A caption that wraps onto a second line changes the dock's height too, and is handled the same way.

### 4. Status is resolved by one pure function

A new `resources/js/components/chat-interface/chatStatus.ts` exports `resolveChatStatus(input): ChatStatus`, where `ChatStatus` is `{ kind, tone, text, moving }`, and `tone` is `ready | idle | working | warning | error`. It implements the precedence in the `chat-status-readout` spec, minus dictation, from inputs `ChatInterface` already has: `isExpired`, `error`, `isStreaming`, `loadingMessage`, `streamingBlocks`, `streamingToolPanels`, `modelStatus`, `isCheckingModelStatus`, `isWarmingModel`, `isAuthenticated`.

For the ready state, `text` is what the caption shows: the model's name for a signed-in viewer when the status names one, otherwise an empty string. `StatusCaption` adds the words "Ready" for assistive technology (decision 6).

`loadingMessage` means two things today. While `isStreaming` it is a server status frame; otherwise it is warm-up text. The function keeps that split, matching what `ModelStatusDisplay` showed.

"Newest thing streamed" uses the fact that `useChatStream` clears `streamingBlocks` whenever a tool call arrives: if there are blocks, the last block's type decides (text means writing, reasoning means thinking); if there are none and the last tool panel has no `output`, a tool is running; otherwise thinking. This stays correct for surfaces that never send tool result frames, where a panel's `output` stays undefined after text has started.

Keeping it pure means the wording and order live in one file that can be read against the spec, and the hooks are untouched (`chat-component-organization` still holds).

### 5. Dictation reports up; the composer merges it

`useDictation` stays inside `DictationControl`, because it needs `voice.config` and `DictationControl` only renders when `voice` is present (hooks can't be conditional in `ChatInputArea`). `DictationControl` drops its absolutely positioned caption and its absolute wrapper, renders only the button (or spinner) for the action margin, and gains `onStatusChange(status: ChatStatus | null)`, called from an effect when its state, elapsed time or message changes. Recording and transcribing report the `working` tone with `moving: true`, and a problem reports the `error` tone. `ChatInputArea` takes a new optional `status?: ChatStatus` prop from `ChatInterface` and shows the dictation status instead, unless the chat's status is `expired`. The spec's order (expiry, then dictation, then everything else) falls out of that.

Esc handling doesn't change: `DictationControl`'s capture-phase listener cancels a recording before `ChatInterface`'s key handler would stop a reply.

### 6. The rail and the caption

Two colocated components render the readout from the same `ChatStatus`.

`chat-interface/StatusRail.tsx` is the 3px rail, `aria-hidden`. Colour by tone:

| Tone | Rail |
| --- | --- |
| `ready` | Full width `primary.main`, still |
| `idle` | Full width `divider`, still |
| `working` | `divider` track with a `secondary.main` segment a quarter of the rail wide |
| `warning` | Full width `warning.main`, still |
| `error` | Full width `error.main`, still |

When `moving`, the segment travels left to right with an emotion `keyframes` animation on `transform: translateX(-100%) → translateX(400%)`, 1.6s linear, repeating; the rail's `overflow: hidden` clips it, so no absolute positioning is needed. Under `@media (prefers-reduced-motion: reduce)` the animation is off and the segment is parked in the middle (`translateX(150%)`). A `working` status that isn't `moving` (checking the model) shows the parked segment.

`chat-interface/StatusCaption.tsx` is the caption line under the surface: a flex row with the words on the left and the keyboard hint (decision 8) on the right. The words are 13px `text.secondary`; error words use `error.main`, and warning words use `warning.dark` darkened by 20% through MUI's `darken()` (on its own `warning.dark` is about 3.8:1 on white, under AA's 4.5:1 for text this size). Recording leads the words with an 8px `error.main` dot, as today. Warning and error words wrap across the full width (the hint is hidden then); other tones are one line with an ellipsis.

Accessibility: the words sit in a `role="status"` element with `aria-live="polite"`. For the error tone they render inside a `role="alert"` element so the failure is announced assertively. In the ready state, visually hidden text supplies the words the caption leaves out: "Ready on " before the model name, or "Ready" alone for guests, so the state is never carried by the rail's colour alone.

All colours are palette names that both the admin theme and the frozen chat theme define, or are derived from them. `warning.dark` comes from MUI's defaults in both.

The warm-up text `useModelStatus` sets becomes "Waking the model. The first reply can take a minute.", the wording on frame `4:2`. Only the string changes; the hook's behaviour does not.

### 7. Buttons in the margin

Send is a 36px `IconButton` with `primary.main` background and `primary.contrastText` icon, and `action.disabledBackground` when disabled. Stop is the same size, outlined in `primary.main`. Microphone is a plain 36px `IconButton` to the left of Send. Corner radius comes from `theme.shape.borderRadius`. Session expiry renders a small outlined `Button` "Refresh" (`window.location.reload()`) in the margin in place of both. The submit `button` keeps `type="submit"` so Enter-in-form behaviour is unchanged.

### 8. The keyboard hint

The caption's right side reads "Ctrl+Enter sends", or "⌘+Enter sends" on Apple platforms (detected once from `navigator.userAgentData?.platform`, falling back to `navigator.userAgent`). It shows in the `ready` and `idle` tones, when Send is what Ctrl/Cmd+Enter would trigger. It is hidden in every other state, because those sentences need the width and Ctrl+Enter doesn't send while a reply streams, and it is hidden below the `sm` breakpoint, where a keyboard is unlikely. Frame `4:2` also shows the hint while a reply streams; it is left out there on purpose.

### 9. Phone cap

`ChatInputArea` reads `useMediaQuery(theme.breakpoints.down("sm"))` and uses `maxRows={isFocused ? (isNarrow ? 5 : 8) : 1}`. The rest of `chat-input-focus-resize` is untouched.

### 10. Slots

`beforeSend` stays above the surface. The caption line sits directly under the surface, and the `afterSend` caption (the public chat's persona note) moves below the caption line, keeping its right alignment.

### 11. What gets deleted

`ModelStatusDisplay.tsx` and `chat-interface/SessionExpiryBanner.tsx` are removed once `ChatInterface` stops importing them (nothing else does). The `LinearProgress` blocks in `chat-message-bubble/BlockContent.tsx` and `LegacyContent.tsx` are removed, along with their `role="status"` wrappers, since the caption now announces streaming.

## Risks / Trade-offs

- [The rail is easy to miss and depends on colour] → The caption carries every state in words and is the live region; the rail is decoration on top.
- [The travelling segment is close to the progress bar it replaces] → It is 3px, sits on the composer rather than in the reply, and holds still under reduced motion. Accepted with the direction.
- [The streaming indicator sits under the transcript, not beside the reply] → It is always directly below the newest message because the transcript is pinned, and the Stop button is a second signal.
- [`ResizeObserver` firing on every keystroke while the composer grows] → It only calls `scrollTo` when the list is at the bottom, and the height only changes when a line wraps.
- [`:focus-within` lights the surface when the microphone has focus, not just the text] → Acceptable, and arguably right: the surface is the control being used. Height collapse still keys off the text area's own focus.
- [Showing the model name could disclose provider details on the public chat] → The name only shows to signed-in viewers.
- [Platform detection is a guess (`userAgentData` is Chromium-only, so others fall back to the user agent string)] → A wrong guess changes one word of a hint, not behaviour.
- [Two themes] → Only palette names both define are used; screenshots are taken under both (an admin chat and `/chats/<bot>`).
- [No JS test runner] → `resolveChatStatus` is pure and small enough to check by hand against the spec's table, and the tasks list the states to walk through in the browser.

## Migration Plan

Frontend only. Ships with the normal deploy (`npm run build`); no migrations, no queue restart needed for this change. Rollback is reverting the commit and rebuilding.
