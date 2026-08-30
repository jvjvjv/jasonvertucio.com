## Context

`ChatInputArea.tsx` renders a MUI `TextField multiline minRows={2}`. MUI's multiline `TextField` is backed by `react-textarea-autosize` under `InputBase`: it measures content in a hidden shadow element and sets the visible `<textarea>`'s `height` via an inline style on every change, growing (and, above `minRows`, shrinking) to fit content — but with no fixed line cap unless `maxRows` is set, and with no transition, so height changes snap instantly. There is currently no focus-tracking at all in this component (no `onFocus`/`onBlur`, no local state).

## Goals / Non-Goals

**Goals:**
- Collapsed (unfocused): the field renders at one line's height, regardless of how much text it holds.
- Expanded (focused): the field auto-grows as the user types, up to a sensible cap, same as today's unbounded-but-2-line-minimum behavior except starting from one line and actually capped.
- Every height change — the focus-driven collapse/expand transition and the per-keystroke growth while typing and focused — animates instead of snapping.

**Non-Goals:**
- Changing `ChatInterface.tsx`'s static `messagePadding` to track the input's live height. It doesn't today either; out of scope for this focused change (see proposal Impact).
- Any change to the ChatBots→Agents rename or the broader Claude.ai-style redesign — both explicitly deferred, separate efforts.
- Changing send/stop button placement, slots, or keyboard handling.

## Decisions

### Track focus locally; drive `minRows`/`maxRows` from it
Add `const [isFocused, setIsFocused] = useState(false)` in `ChatInputArea`, wired to the `TextField`'s `onFocus`/`onBlur`. Render with `minRows={1}` (unconditionally) and `maxRows={isFocused ? 8 : 1}`. Collapsing via `maxRows={1}` (not `minRows`) is what actually clamps the rendered height to one line regardless of content while unfocused; `minRows` alone only sets a floor, not a ceiling. `8` for the focused cap is an arbitrary-but-reasonable bound matching common chat-composer conventions (roughly a third of a typical viewport); revisit if it feels wrong once seen.

**Alternative considered**: collapse by conditionally rendering a different component (e.g. swap to a single-line `TextField` when unfocused). Rejected — swapping components on focus change means losing focus/selection state and typing position, and MUI's own autosize path already handles single-vs-multi-line height via `maxRows`; no need to reinvent it.

### Animate via a CSS transition on the textarea element, not a manual height/JS-driven animation
Add `sx: { "& .MuiInputBase-inputMultiline": { transition: "height 150ms ease" } }` (alongside the existing `paddingBottom` override on that same selector) to the `TextField`. `react-textarea-autosize` sets `height` as an inline style on the actual `<textarea>` on every recalculation; a plain CSS transition on that property animates every change it makes — the focus-driven collapse/expand and the per-keystroke growth alike — with no additional JS.

**Alternative considered**: a JS-driven height animation (e.g. a spring/tween library, or manually interpolating `minRows`/`maxRows`). Rejected as unnecessary complexity — CSS `transition` on the property `react-textarea-autosize` already mutates is sufficient and this codebase already uses this exact `sx={{ transition: ... }}` pattern elsewhere (`DesktopNavItem.tsx`).

### Collapsed state keeps existing text, just visually truncated
When a multi-line draft exists and the field loses focus, the text is not cleared or altered — it's still there, just not fully visible at one line's height (browser's native textarea scroll/clip behavior). Refocusing re-expands and the full draft is visible and editable again. This matches the proposal's "collapses to a single line," and is simplest: no scroll-position bookkeeping needed since nothing is destroyed.

## Risks / Trade-offs

- **[Risk]** `react-textarea-autosize`'s inline `height` style might get fought by MUI's own layout recalculation in a way that makes the CSS transition look janky (e.g. a resize-triggered re-measure interrupting the transition mid-animation) → **Mitigation**: this is a small, purely visual change with low blast radius — verify by hand in a running dev server (per project convention for UI changes) before considering this done; if janky, fall back to a shorter duration or `transition-timing-function: linear` before reaching for a JS-driven approach.
- **[Trade-off]** A fixed `maxRows={8}` cap while focused is a behavior change from today's unbounded growth — a very long pasted message will now scroll inside the box past 8 lines instead of pushing the box (and overlapping content below it, per the Non-Goals note on `messagePadding`) taller indefinitely. Considered acceptable: today's unbounded growth already overlaps the message list once it exceeds the static `messagePadding` reservation, so a cap is a mild improvement, not a regression.
