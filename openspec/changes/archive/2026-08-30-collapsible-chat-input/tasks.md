## 1. Composer focus/resize behavior

- [x] 1.1 In `resources/js/components/ChatInputArea.tsx`, add `const [isFocused, setIsFocused] = useState(false)` and wire `onFocus={() => setIsFocused(true)}` / `onBlur={() => setIsFocused(false)}` onto the `TextField`.
- [x] 1.2 Set `minRows={1}` and `maxRows={isFocused ? 8 : 1}` on the `TextField` (replacing the current `minRows={2}` with no `maxRows`).
- [x] 1.3 Add a CSS transition on the textarea's height by extending the existing `sx` override for `& .MuiInputBase-inputMultiline` (the same selector already used for the `paddingBottom` override) with `transition: "height 150ms ease"`.

## 2. Verification

- [x] 2.1 Run `npx tsc --noEmit -p .` — no new type errors.
- [x] 2.2 Run `npm run build` — succeeds.
- [x] 2.3 Start the dev server and manually verify in a chat page: composer sits at one line unfocused; clicking in expands it; typing multiple lines grows it smoothly (not a snap); typing past ~8 rows caps the height and scrolls; blurring a multi-line draft collapses it back to one line with a visible animation, and refocusing re-expands the full draft.
- [x] 2.4 Confirm no regression to send/stop button click targets or Enter/Shift+Enter keyboard handling (`onKeyDown` is untouched, but verify visually since the button is absolutely positioned relative to the box that now changes height more often).
